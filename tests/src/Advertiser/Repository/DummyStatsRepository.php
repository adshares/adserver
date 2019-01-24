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

namespace Adshares\Tests\Advertiser\Repository;

use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\User;
use Adshares\Advertiser\Dto\Result\Calculation;
use Adshares\Advertiser\Dto\Result\DataCollection;
use Adshares\Advertiser\Dto\Result\DataEntry;
use Adshares\Advertiser\Dto\Result\StatsResult;
use Adshares\Advertiser\Dto\Result\ChartResult;
use Adshares\Advertiser\Dto\Result\Total;
use Adshares\Advertiser\Repository\StatsRepository;
use DateTime;

class DummyStatsRepository implements StatsRepository
{
    const USER_EMAIL = 'postman@dev.dev';

    public function fetchView(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        $data = [
            [1, 1, 1],
            [2, 2, 2],
            [3, 3, 3],
            [4, 4, 4],
        ];

        if ($campaignId) {
            $data = $this->setDataForCampaign($data);
        }

        return new ChartResult($data);
    }

    private function setDataForCampaign(array $data): array
    {
        foreach ($data as &$entry) {
            foreach ($entry as &$value) {
                $value = 100 + $value;
            }
        }

        return $data;
    }

    public function fetchClick(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        $data = [
            [11, 11, 11],
            [21, 21, 21],
            [31, 31, 31],
            [41, 41, 41],
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
            [12, 12, 12],
            [22, 22, 22],
            [32, 32, 32],
            [42, 42, 42],
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
            [13, 13, 13],
            [23, 23, 23],
            [33, 33, 33],
            [43, 43, 43],
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
            [14, 14, 14],
            [24, 24, 24],
            [34, 34, 34],
            [44, 44, 44],
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
            [15, 15, 15],
            [25, 25, 25],
            [35, 35, 35],
            [45, 45, 45],
        ];

        return new ChartResult($data);
    }

    public function fetchStats(
        string $advertiserId,
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
        }

        $campaigns = $user->campaigns;

        $campaignUuid = $campaigns[0]->uuid;

        $data = [
            new DataEntry(new Calculation(1, 1, 1, 1, 1, 1), $campaignUuid, $bannerId1 ?? null),
            new DataEntry(new Calculation(2, 2, 2, 2, 2, 2), $campaignUuid, $bannerId2 ?? null),
            new DataEntry(new Calculation(3, 3, 3, 3, 3, 3), $campaignUuid, $bannerId3 ?? null),
            new DataEntry(new Calculation(4, 4, 4, 4, 4, 4), $campaignUuid, $bannerId4 ?? null),
        ];

        return new DataCollection($data);
    }

    public function fetchStatsTotal(
        string $advertiserId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): Total {

        $calculation = new Calculation(1, 1, 1, 1, 1, 1);

        return new Total($calculation);
    }
}
