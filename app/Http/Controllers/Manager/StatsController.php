<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types = 1);

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Advertiser\Dto\Input\ChartInput as AdvertiserChartInput;
use Adshares\Advertiser\Dto\Input\StatsInput as AdvertiserStatsInput;
use Adshares\Advertiser\Service\ChartDataProvider as AdvertiserChartDataProvider;
use Adshares\Advertiser\Service\StatsDataProvider as AdvertiserStatsDataProvider;
use Adshares\Advertiser\Dto\Input\InvalidInputException as AdvertiserInvalidInputException;
use Adshares\Publisher\Dto\Input\ChartInput as PublisherChartInput;
use Adshares\Publisher\Dto\Input\StatsInput as PublisherStatsInput;
use Adshares\Publisher\Dto\Input\InvalidInputException as PublisherInvalidInputException;
use Adshares\Publisher\Service\ChartDataProvider as PublisherChartDataProvider;
use Adshares\Publisher\Service\StatsDataProvider as PublisherStatsDataProvider;
use Closure;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StatsController extends Controller
{
    /** @var AdvertiserChartDataProvider */
    private $advertiserChartDataProvider;

    /** @var AdvertiserStatsDataProvider */
    private $advertiserStatsDataProvider;

    /** @var PublisherChartDataProvider */
    private $publisherChartDataProvider;

    /** @var PublisherStatsDataProvider */
    private $publisherStatsDataProvider;

    public function __construct(
        AdvertiserChartDataProvider $advertiserChartDataProvider,
        AdvertiserStatsDataProvider $advertiserStatsDataProvider,
        PublisherChartDataProvider $publisherChartDataProvider,
        PublisherStatsDataProvider $publisherStatsDataProvider
    ) {
        $this->advertiserChartDataProvider = $advertiserChartDataProvider;
        $this->advertiserStatsDataProvider = $advertiserStatsDataProvider;
        $this->publisherChartDataProvider = $publisherChartDataProvider;
        $this->publisherStatsDataProvider = $publisherStatsDataProvider;
    }

    public function advertiserChart(
        Request $request,
        string $type,
        string $resolution,
        string $dateStart,
        string $dateEnd
    ): JsonResponse {
        $from = $this->createDateTime($dateStart);
        $to = $this->createDateTime($dateEnd);
        $campaignId = $this->getCampaignIdFromRequest($request);

        /** @var User $user */
        $user = Auth::user();

        $this->validateChartInputParameters($from, $to);
        $this->validateUserAsAdvertiser($user);

        try {
            $input = new AdvertiserChartInput(
                $user->uuid,
                $type,
                $resolution,
                $from,
                $to,
                $campaignId
            );
        } catch (AdvertiserInvalidInputException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $result = $this->advertiserChartDataProvider->fetch($input);

        return new JsonResponse($result->toArray());
    }

    public function publisherChart(
        Request $request,
        string $type,
        string $resolution,
        string $dateStart,
        string $dateEnd
    ): JsonResponse {
        $from = $this->createDateTime($dateStart);
        $to = $this->createDateTime($dateEnd);
        $siteId = $this->getSiteIdFromRequest($request);

        /** @var User $user */
        $user = Auth::user();

        $this->validateChartInputParameters($from, $to);
        $this->validateUserAsPublisher($user);

        try {
            $input = new PublisherChartInput(
                $user->uuid,
                $type,
                $resolution,
                $from,
                $to,
                $siteId
            );
        } catch (PublisherInvalidInputException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $result = $this->publisherChartDataProvider->fetch($input);

        return new JsonResponse($result->toArray());
    }

    private function createDateTime(string $dateInISO8601Format): ?DateTime
    {
        $date = DateTime::createFromFormat(DateTime::ATOM, $dateInISO8601Format);

        if (!$date) {
            return null;
        }

        return $date;
    }

    private function getCampaignIdFromRequest(Request $request): ?string
    {
        $campaignId = $request->input('campaign_id');

        if (!$campaignId) {
            return null;
        }

        $campaign = Campaign::find($campaignId);

        if (!$campaign) {
            throw new NotFoundHttpException('Campaign does not exists.');
        }

        return $campaign->uuid;
    }

    private function validateChartInputParameters(
        ?DateTime $dateStart,
        ?DateTime $dateEnd
    ): void {
        if (!$dateStart) {
            throw new BadRequestHttpException('Bad format of start date.');
        }

        if (!$dateEnd) {
            throw new BadRequestHttpException('Bad format of end date.');
        }
    }

    public function advertiserStats(
        Request $request,
        string $dateStart,
        string $dateEnd
    ): JsonResponse {
        $from = $this->createDateTime($dateStart);
        $to = $this->createDateTime($dateEnd);
        $campaignId = $this->getCampaignIdFromRequest($request);

        /** @var User $user */
        $user = Auth::user();

        $this->validateChartInputParameters($from, $to);
        $this->validateUserAsAdvertiser($user);

        try {
            $input = new AdvertiserStatsInput(
                $user->uuid,
                $from,
                $to,
                $campaignId
            );
        } catch (AdvertiserInvalidInputException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $result = $this->advertiserStatsDataProvider->fetch($input);

        $data = array_map($this->callbackTransformingId(), $result->getData());
        $data = array_filter($data, $this->callbackFilteringNullFromAdvertiserStats());

        return new JsonResponse($data);
    }

    public function advertiserStatsWithTotal(
        Request $request,
        string $dateStart,
        string $dateEnd
    ): JsonResponse {
        $from = $this->createDateTime($dateStart);
        $to = $this->createDateTime($dateEnd);
        $campaignId = $this->getCampaignIdFromRequest($request);

        /** @var User $user */
        $user = Auth::user();

        $this->validateChartInputParameters($from, $to);
        $this->validateUserAsAdvertiser($user);

        try {
            $input = new AdvertiserStatsInput(
                $user->uuid,
                $from,
                $to,
                $campaignId
            );
        } catch (AdvertiserInvalidInputException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $result = $this->advertiserStatsDataProvider->fetch($input);

        $total = $this->transformPublicIdToPrivateId($result->getTotal());
        $data = array_map($this->callbackTransformingId(), $result->getData());
        $data = array_filter($data, $this->callbackFilteringNullFromAdvertiserStats());

        return new JsonResponse(['total' => $total, 'data' => $data]);
    }

    public function publisherStats(
        Request $request,
        string $dateStart,
        string $dateEnd
    ): JsonResponse {
        $from = $this->createDateTime($dateStart);
        $to = $this->createDateTime($dateEnd);
        $siteId = $this->getSiteIdFromRequest($request);

        /** @var User $user */
        $user = Auth::user();

        $this->validateChartInputParameters($from, $to);
        $this->validateUserAsPublisher($user);

        try {
            $input = new PublisherStatsInput(
                $user->uuid,
                $from,
                $to,
                $siteId
            );
        } catch (PublisherInvalidInputException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $result = $this->publisherStatsDataProvider->fetch($input);

        $data = array_map($this->callbackTransformingId(), $result->getData());
        $data = array_filter($data, $this->callbackFilteringNullFromPublisherStats());

        return new JsonResponse($data);
    }

    public function publisherStatsWithTotal(
        Request $request,
        string $dateStart,
        string $dateEnd
    ): JsonResponse {
        $from = $this->createDateTime($dateStart);
        $to = $this->createDateTime($dateEnd);
        $siteId = $this->getSiteIdFromRequest($request);

        /** @var User $user */
        $user = Auth::user();

        $this->validateChartInputParameters($from, $to);
        $this->validateUserAsPublisher($user);

        try {
            $input = new PublisherStatsInput(
                $user->uuid,
                $from,
                $to,
                $siteId
            );
        } catch (PublisherInvalidInputException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $result = $this->publisherStatsDataProvider->fetch($input);

        $total = $this->transformPublicIdToPrivateId($result->getTotal());
        $data = array_map($this->callbackTransformingId(), $result->getData());
        $data = array_filter($data, $this->callbackFilteringNullFromPublisherStats());

        return new JsonResponse(['total' => $total, 'data' => $data]);
    }

    private function transformPublicIdToPrivateId(array $item): array
    {
        if (isset($item['campaignId'])) {
            $campaign = Campaign::fetchByUuid($item['campaignId']);
            $item['campaignId'] = $campaign->id ?? null;
        }

        if (isset($item['bannerId'])) {
            $banner = Banner::fetchBanner($item['bannerId']);
            $item['bannerId'] = $banner->id ?? null;
        }

        if (isset($item['siteId'])) {
            $site = Site::fetchByPublicId($item['siteId']);
            $item['siteId'] = $site->id ?? null;
        }

        if (isset($item['zoneId'])) {
            $zone = Zone::fetchByPublicId($item['zoneId']);
            $item['zoneId'] = $zone->id ?? null;
        }

        return $item;
    }

    private function getSiteIdFromRequest(Request $request): ?string
    {
        $siteId = $request->input('site_id');

        if (!$siteId) {
            return null;
        }

        $site = Site::find($siteId);

        if (!$site) {
            throw new NotFoundHttpException('Site does not exists.');
        }

        return $site->uuid;
    }

    private function callbackTransformingId(): Closure
    {
        return function ($item) {
            return $this->transformPublicIdToPrivateId($item);
        };
    }

    private function callbackFilteringNullFromAdvertiserStats(): Closure
    {
        return function (array $item) {
            if ((array_key_exists('campaignId', $item) && is_null($item['campaignId']))
                || (array_key_exists('bannerId', $item) && is_null($item['bannerId']))) {
                return false;
            }

            return true;
        };
    }

    private function callbackFilteringNullFromPublisherStats(): Closure
    {
        return function (array $item) {
            if ((array_key_exists('siteId', $item) && is_null($item['siteId']))
                || (array_key_exists('zoneId', $item) && is_null($item['zoneId']))) {
                return false;
            }

            return true;
        };
    }

    private function validateUserAsPublisher(User $user): void
    {
        if (!$user->isPublisher()) {
            throw new AccessDeniedHttpException(
                sprintf(
                    'User %s is not authorized to access this resource.',
                    $user->email
                )
            );
        }
    }

    private function validateUserAsAdvertiser(User $user): void
    {
        if (!$user->isAdvertiser()) {
            throw new AccessDeniedHttpException(
                sprintf(
                    'User %s is not authorized to access this resource.',
                    $user->email
                )
            );
        }
    }
}
