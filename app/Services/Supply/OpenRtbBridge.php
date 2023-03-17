<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Services\Supply;

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\BridgePayment;
use Adshares\Supply\Application\Dto\FoundBanners;
use Adshares\Supply\Application\Dto\ImpressionContext;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class OpenRtbBridge
{
    private const PAYMENTS_PATH = '/payment-reports';
    private const SERVE_PATH = '/serve';

    public static function isActive(): bool
    {
        return null !== config('app.open_rtb_bridge_account_address')
            && null !== config('app.open_rtb_bridge_url');
    }

    public function replaceOpenRtbBanners(FoundBanners $foundBanners, ImpressionContext $context): FoundBanners
    {
        $accountAddress = config('app.open_rtb_bridge_account_address');
        $openRtbBanners = [];
        foreach ($foundBanners as $index => $foundBanner) {
            if (null !== $foundBanner && $accountAddress === $foundBanner['pay_from']) {
                $openRtbBanners[(string)$index] = [
                    'request_id' => (string)$index,
                    'creative_id' => $foundBanner['demandId'],
                ];
            }
        }
        if (empty($openRtbBanners)) {
            return $foundBanners;
        }
        try {
            $response = Http::post(
                config('app.open_rtb_bridge_url') . self::SERVE_PATH,
                [
                    'context' => $context->toArray(),
                    'requests' => $openRtbBanners,
                ],
            );
            if (
                BaseResponse::HTTP_OK === $response->status()
                && $this->isOpenRtbAuctionResponseValid($content = $response->json(), $openRtbBanners)
            ) {
                foreach ($content as $entry) {
                    $externalId = $entry['ext_id'];
                    $foundBanner = $foundBanners->get((int)$entry['request_id']);
                    foreach (['click_url', 'view_url'] as $field) {
                        $foundBanner[$field] = Utils::addUrlParameter(
                            Utils::removeUrlParameter($foundBanner[$field], 'r'),
                            'extid',
                            $externalId,
                        );
                    }
                    $foundBanner['serve_url'] = $entry['serve_url'];
                    $foundBanners->set((int)$entry['request_id'], $foundBanner);
                    unset($openRtbBanners[$entry['request_id']]);
                }
            }
        } catch (HttpClientException $exception) {
            Log::error(sprintf('Replacing OpenRtb banner failed: %s', $exception->getMessage()));
        }
        foreach ($openRtbBanners as $index => $serveUrl) {
            $foundBanners->set($index, null);
        }
        return $foundBanners;
    }

    public function fetchAndStorePayments(): void
    {
        try {
            $response = Http::get(config('app.open_rtb_bridge_url') . self::PAYMENTS_PATH);
            if (
                BaseResponse::HTTP_OK === $response->status()
                && $this->isPaymentResponseValid($content = $response->json())
            ) {
                if (empty($content)) {
                    return;
                }
                $paymentIds = array_map(fn (array $entry) => $entry['id'], $content);
                $accountAddress = config('app.open_rtb_bridge_account_address');
                $bridgePayments = BridgePayment::fetchByAddressAndPaymentIds($accountAddress, $paymentIds)
                    ->keyBy('payment_id');
                foreach ($content as $entry) {
                    $paymentId = $entry['id'];
                    if (null === ($bridgePayment = $bridgePayments->get($paymentId))) {
                        BridgePayment::register(
                            $accountAddress,
                            $paymentId,
                            DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $entry['created_at']),
                            $entry['value'],
                            'done' === $entry['status'] ? BridgePayment::STATUS_NEW : BridgePayment::STATUS_RETRY,
                        );
                    } else {
                        /** @var $bridgePayment BridgePayment */
                        if (BridgePayment::STATUS_RETRY === $bridgePayment->status && 'done' === $entry['status']) {
                            $bridgePayment->payment_time =
                                DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $entry['created_at']);
                            $bridgePayment->amount = $entry['value'];
                            $bridgePayment->status = BridgePayment::STATUS_NEW;
                            $bridgePayment->saveOrFail();
                        }
                    }
                }
            }
        } catch (HttpClientException $exception) {
            Log::error(sprintf('Fetching payments from bridge failed: %s', $exception->getMessage()));
        }
    }

    private function isPaymentResponseValid(mixed $content): bool
    {
        if (!is_array($content)) {
            Log::error('Invalid bridge payments response: body is not an array');
            return false;
        }
        foreach ($content as $entry) {
            if (!is_array($entry)) {
                Log::error('Invalid bridge payments response: entry is not an array');
                return false;
            }
            $fields = [
                'id',
                'created_at',
                'status',
                'value',
            ];
            foreach ($fields as $field) {
                if (!array_key_exists($field, $entry)) {
                    Log::error(sprintf('Invalid bridge payments response: missing key %s', $field));
                    return false;
                }
            }
            if (!is_string($entry['status'])) {
                Log::error('Invalid bridge payments response: status is not a string');
                return false;
            }
            if (false === DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $entry['created_at'])) {
                Log::error('Invalid bridge payments response: created_at is not in ISO8601 format');
                return false;
            }
        }
        return true;
    }

    private function isOpenRtbAuctionResponseValid(mixed $content, array $openBtbBanners): bool
    {
        if (!is_array($content)) {
            Log::error('Invalid OpenRTB response: body is not an array');
            return false;
        }
        foreach ($content as $entry) {
            if (!is_array($entry)) {
                Log::error('Invalid OpenRTB response: entry is not an array');
                return false;
            }
            $fields = [
                'ext_id',
                'request_id',
                'serve_url',
            ];
            foreach ($fields as $field) {
                if (!isset($entry[$field])) {
                    Log::error(sprintf('Invalid OpenRTB response: missing key %s', $field));
                    return false;
                }
                if (!is_string($entry[$field])) {
                    Log::error(sprintf('Invalid OpenRTB response: %s is not a string', $field));
                    return false;
                }
            }
            if (!array_key_exists($entry['request_id'], $openBtbBanners)) {
                Log::error(sprintf('Invalid OpenRTB response: request %s is not known', $entry['request_id']));
                return false;
            }
        }
        return true;
    }

    public function getEventRedirectUrl(string $url): ?string
    {
        $redirectUrl = null;
        try {
            $response = Http::get($url);
            $statusCode = $response->status();
            if (BaseResponse::HTTP_OK === $statusCode) {
                $redirectUrl = $response->json('redirect_url');
            } else {
                if (BaseResponse::HTTP_NO_CONTENT !== $statusCode) {
                    Log::error(sprintf('DSP bridge event notification failed: %d: %s', $statusCode, $response->body()));
                }
            }
        } catch (HttpClientException $exception) {
            Log::error(sprintf('DSP bridge event notification failed: client exception: %s', $exception->getMessage()));
        }
        return $redirectUrl;
    }
}
