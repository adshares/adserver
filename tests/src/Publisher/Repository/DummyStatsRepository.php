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

namespace Adshares\Tests\Publisher\Repository;

use Adshares\Publisher\Dto\StatsResult;
use Adshares\Publisher\Dto\ChartResult;
use Adshares\Publisher\Repository\StatsRepository;
use DateTime;

class DummyStatsRepository implements StatsRepository
{
    const USER_EMAIL = 'postman@dev.dev';

    public function fetchView(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $data = [
            [1, 1, 1],
            [2, 2, 2],
            [3, 3, 3],
            [4, 4, 4],
        ];

        if ($siteId) {
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
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $data = [
            [11, 11, 11],
            [21, 21, 21],
            [31, 31, 31],
            [41, 41, 41],
        ];

        return new ChartResult($data);
    }

    public function fetchRpc(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $data = [
            [12, 12, 12],
            [22, 22, 22],
            [32, 32, 32],
            [42, 42, 42],
        ];

        return new ChartResult($data);
    }

    public function fetchRpm(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
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
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
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
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
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
        string $publisherId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): StatsResult {
        $data = [
            [1, 1, 1, 1, 1, '0F852769BC3E42E1A1D0DF420F9E794B', '6B30304F390A40A6B51CC2015250A7E1'],
            [2, 2, 2, 2, 2, '0F852769BC3E42E1A1D0DF420F9E794B', '6B30304F390A40A6B51CC2015250A7E2'],
            [3, 3, 3, 3, 3, '0F852769BC3E42E1A1D0DF420F9E794B', '6B30304F390A40A6B51CC2015250A7E3'],
            [4, 4, 4, 4, 4, '0F852769BC3E42E1A1D0DF420F9E794B', '6B30304F390A40A6B51CC2015250A7E4'],
        ];

        return new StatsResult($data);
    }
}
