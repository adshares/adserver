<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

declare(strict_types=1);

namespace Adshares\Tests\Advertiser\Repository;

use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\User;
use Adshares\Advertiser\Dto\Result\ChartResult;
use Adshares\Advertiser\Dto\Result\Stats\Calculation;
use Adshares\Advertiser\Dto\Result\Stats\ConversionDataCollection;
use Adshares\Advertiser\Dto\Result\Stats\DataCollection;
use Adshares\Advertiser\Dto\Result\Stats\DataEntry;
use Adshares\Advertiser\Dto\Result\Stats\Total;
use Adshares\Advertiser\Repository\StatsRepository;
use DateTime;

class DummyStatsRepository implements StatsRepository
{
    public const USER_EMAIL = 'postman@dev.dev';

    public function fetchView(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        $data = [
            ['2019-01-01T15:00:00+00:00', 1],
            ['2019-01-01T16:00:00+00:00', 2],
            ['2019-01-01T17:00:00+00:00', 3],
            ['2019-01-01T18:00:00+00:00', 4],
        ];

        return new ChartResult($data);
    }

    public function fetchViewAll(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        return $this->fetchView($advertiserId, $resolution, $dateStart, $dateEnd, $campaignId);
    }

    public function fetchViewInvalidRate(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        $data = [
            ['2019-01-01T15:00:00+00:00', 0.01],
            ['2019-01-01T16:00:00+00:00', 0.31],
            ['2019-01-01T17:00:00+00:00', 0.61],
            ['2019-01-01T18:00:00+00:00', 0.91],
        ];

        return new ChartResult($data);
    }

    public function fetchViewUnique(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        return $this->fetchView($advertiserId, $resolution, $dateStart, $dateEnd, $campaignId);
    }

    public function fetchClick(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        $data = [
            ['2019-01-01T15:00:00+00:00', 11],
            ['2019-01-01T16:00:00+00:00', 21],
            ['2019-01-01T17:00:00+00:00', 31],
            ['2019-01-01T18:00:00+00:00', 41],
        ];

        return new ChartResult($data);
    }

    public function fetchClickAll(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        return $this->fetchClick($advertiserId, $resolution, $dateStart, $dateEnd, $campaignId);
    }

    public function fetchClickInvalidRate(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        $data = [
            ['2019-01-01T15:00:00+00:00', 0.02],
            ['2019-01-01T16:00:00+00:00', 0.32],
            ['2019-01-01T17:00:00+00:00', 0.62],
            ['2019-01-01T18:00:00+00:00', 0.92],
        ];

        return new ChartResult($data);
    }

    public function fetchCpc(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        $data = [
            ['2019-01-01T15:00:00+00:00', 12],
            ['2019-01-01T16:00:00+00:00', 22],
            ['2019-01-01T17:00:00+00:00', 32],
            ['2019-01-01T18:00:00+00:00', 42],
        ];

        return new ChartResult($data);
    }

    public function fetchCpm(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        $data = [
            ['2019-01-01T15:00:00+00:00', 13],
            ['2019-01-01T16:00:00+00:00', 23],
            ['2019-01-01T17:00:00+00:00', 33],
            ['2019-01-01T18:00:00+00:00', 43],
        ];

        return new ChartResult($data);
    }

    public function fetchSum(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        $data = [
            ['2019-01-01T15:00:00+00:00', 14],
            ['2019-01-01T16:00:00+00:00', 24],
            ['2019-01-01T17:00:00+00:00', 34],
            ['2019-01-01T18:00:00+00:00', 44],
        ];

        return new ChartResult($data);
    }

    public function fetchSumPayment(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        $data = [
            ['2019-01-01T15:00:00+00:00', 141],
            ['2019-01-01T16:00:00+00:00', 241],
            ['2019-01-01T17:00:00+00:00', 341],
            ['2019-01-01T18:00:00+00:00', 441],
        ];

        return new ChartResult($data);
    }

    public function fetchCtr(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        $data = [
            ['2019-01-01T15:00:00+00:00', 0.03],
            ['2019-01-01T16:00:00+00:00', 0.33],
            ['2019-01-01T17:00:00+00:00', 0.63],
            ['2019-01-01T18:00:00+00:00', 0.93],
        ];

        return new ChartResult($data);
    }

    public function fetchStats(
        ?string $advertiserId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): DataCollection {
        $user = User::fetchByEmail(self::USER_EMAIL);

        if ($campaignId) {
            $campaign = Campaign::fetchByUuid($campaignId);
            $banners = $campaign->banners;

            $bannerId1 = $banners[0]->uuid;
            $bannerId2 = $banners[1]->uuid;
            $bannerId3 = $banners[2]->uuid;
            $bannerId4 = $banners[3]->uuid;
            $bannerName1 = $banners[0]->name;
            $bannerName2 = $banners[1]->name;
            $bannerName3 = $banners[2]->name;
            $bannerName4 = $banners[3]->name;
        }

        $campaigns = $user->campaigns;
        if ($campaigns->isEmpty()) {
            return new DataCollection([]);
        }

        $campaignId = $campaigns[0]->id;
        $campaignName = $campaigns[0]->name;

        $data = [
            new DataEntry(
                new Calculation(1, 1, 1, 1, 1, 1),
                $campaignId,
                $campaignName,
                $bannerId1 ?? null,
                $bannerName1 ?? null
            ),
            new DataEntry(
                new Calculation(2, 2, 2, 2, 2, 2),
                $campaignId,
                $campaignName,
                $bannerId2 ?? null,
                $bannerName2 ?? null
            ),
            new DataEntry(
                new Calculation(3, 3, 3, 3, 3, 3),
                $campaignId,
                $campaignName,
                $bannerId3 ?? null,
                $bannerName3 ?? null
            ),
            new DataEntry(
                new Calculation(4, 4, 4, 4, 4, 4),
                $campaignId,
                $campaignName,
                $bannerId4 ?? null,
                $bannerName4 ?? null
            ),
        ];

        return new DataCollection($data);
    }

    public function fetchStatsTotal(
        ?string $advertiserId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): Total {
        $calculation = new Calculation(1, 1, 1, 1, 1, 1);

        return new Total($calculation);
    }

    public function fetchStatsToReport(
        ?string $advertiserId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): DataCollection {
        // TODO: Implement fetchStatsToReport() method.
    }

    public function fetchStatsConversion(
        int $advertiserId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?int $campaignId = null
    ): ConversionDataCollection {
        // TODO: Implement fetchStatsConversion() method.
    }

    public function aggregateStatistics(DateTime $dateStart, DateTime $dateEnd): void
    {
        // TODO: Implement cacheStatistics() method.
    }
}
