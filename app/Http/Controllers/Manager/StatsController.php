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
use Adshares\Adserver\Models\User;
use Adshares\Advertiser\Dto\ChartInput as AdvertiserChartInput;
use Adshares\Advertiser\Dto\InvalidInputException;
use Adshares\Advertiser\Dto\StatsInput as AdvertiserStatsInput;
use Adshares\Advertiser\Service\ChartDataProvider as AdvertiserChartDataProvider;
use Adshares\Advertiser\Service\StatsDataProvider as AdvertiserStatsDataProvider;
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

    public function __construct(
        AdvertiserChartDataProvider $advertiserChartDataProvider,
        AdvertiserStatsDataProvider $advertiserStatsDataProvider
    ) {
        $this->advertiserChartDataProvider = $advertiserChartDataProvider;
        $this->advertiserStatsDataProvider = $advertiserStatsDataProvider;
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

        $this->validateChartInputParameters($user, $from, $to);

        if (!$user->isAdvertiser()) {
            throw new AccessDeniedHttpException(sprintf(
                'User %s is not authorized to access this resource.',
                $user->email
            ));
        }

        try {
            $input = new AdvertiserChartInput(
                $user->uuid,
                $type,
                $resolution,
                $from,
                $to,
                $campaignId
            );
        } catch (InvalidInputException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $result = $this->advertiserChartDataProvider->fetch($input);

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
        User $user,
        ?DateTime $dateStart,
        ?DateTime $dateEnd
    ): void {
        if (!$dateStart) {
            throw new BadRequestHttpException('Bad format of start date.');
        }

        if (!$dateEnd) {
            throw new BadRequestHttpException('Bad format of end date.');
        }

        if (!$user) {
            throw new NotFoundHttpException('User is not found');
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

        $this->validateChartInputParameters($user, $from, $to);

        if (!$user->isAdvertiser()) {
            throw new AccessDeniedHttpException(sprintf(
                'User %s is not authorized to access this resource.',
                $user->email
            ));
        }

        try {
            $input = new AdvertiserStatsInput(
                $user->uuid,
                $from,
                $to,
                $campaignId
            );
        } catch (InvalidInputException $exception) {
            throw new BadRequestHttpException($exception->getMessage(), $exception);
        }

        $result = $this->advertiserStatsDataProvider->fetch($input)->toArray();

        foreach ($result as &$item) {
            $item = $this->transformUuidIntoInt($item);
        }

        return new JsonResponse($result);
    }

    private function transformUuidIntoInt(array $item): array
    {
        if (isset($item['bannerId'])) {
            $banner = Banner::fetchBanner($item['bannerId']);
            $item['bannerId'] = $banner->id;
        }

        if (isset($item['campaignId'])) {
            $campaign = Campaign::fetchByUuid($item['campaignId']);
            $item['campaignId'] = $campaign->id;
        }

        return $item;
    }
}
