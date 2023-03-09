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

namespace Adshares\Adserver\Services\Demand;

use Adshares\Supply\Application\Dto\FoundBanners;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

class OpenRtbBridge
{
    public static function isActive(): bool
    {
        return null !== config('app.open_rtb_bridge_account_address')
            && null !== config('app.open_rtb_bridge_serve_url');
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
        $response = Http::post(
            config('app.open_rtb_bridge_serve_url'),
            [
                'context' => $context->toArray(),
                'requests' => $openRtbBanners
            ],
        );
        if (
            BaseResponse::HTTP_OK !== $response->status()
            || !$this->isOpenRtbAuctionResponseValid($content = $response->json(), $openRtbBanners)
        ) {
            foreach ($openRtbBanners as $index => $serveUrl) {
                $foundBanners->set($index, null);
            }
            return $foundBanners;
        }
        foreach ($content as $entry) {
            $foundBanner = array_merge(
                $foundBanners->get((int)$entry['request_id']),
                [
                    'click_url' => $entry['click_url'],
                    'serve_url' => $entry['serve_url'],
                    'view_url' => $entry['view_url'],
                ]
            );
            $foundBanners->set((int)$entry['request_id'], $foundBanner);
            unset($openRtbBanners[$entry['request_id']]);
        }
        foreach ($openRtbBanners as $index => $serveUrl) {
            $foundBanners->set($index, null);
        }
        return $foundBanners;
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
                'request_id',
                'click_url',
                'serve_url',
                'view_url',
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
}
