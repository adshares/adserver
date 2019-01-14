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

use Adshares\Advertiser\Dto\StatsResult;
use Adshares\Advertiser\Dto\ChartResult;
use Adshares\Advertiser\Repository\StatsRepository;
use DateTime;

class DummyStatsRepository implements StatsRepository
{

    public function fetchView(
        int $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?int $campaignId = null,
        ?int $bannerId = null
    ): ChartResult {
        $data = [
            [1, 1, 1],
            [2, 2, 2],
            [3, 3, 3],
            [4, 4, 4],
        ];

        return new ChartResult($data);
    }

    public function fetchClick(
        int $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?int $campaignId = null,
        ?int $bannerId = null
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
        int $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?int $campaignId = null,
        ?int $bannerId = null
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
        int $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?int $campaignId = null,
        ?int $bannerId = null
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
        int $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?int $campaignId = null,
        ?int $bannerId = null
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
        int $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?int $campaignId = null,
        ?int $bannerId = null
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
        int $advertiserId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?int $campaignId = null,
        ?int $bannerId = null
    ): StatsResult {
        $campaignId = 1;
        $data = [
            [$campaignId, 1, 1, 1, 1, 1],
            [$campaignId, 2, 2, 2, 2, 2],
            [$campaignId, 3, 3, 3, 3, 3],
        ];

        return new StatsResult($data);
    }
}
