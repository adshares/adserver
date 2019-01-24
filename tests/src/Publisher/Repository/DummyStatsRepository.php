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

use Adshares\Publisher\Dto\Result\ChartResult;
use Adshares\Publisher\Dto\Result\Stats\Calculation;
use Adshares\Publisher\Dto\Result\Stats\DataCollection;
use Adshares\Publisher\Dto\Result\Stats\DataEntry;
use Adshares\Publisher\Dto\Result\Stats\Total;
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
            ['2019-01-01T15:00:00+00:00', 1, 1],
            ['2019-01-01T16:00:00+00:00', 2, 2],
            ['2019-01-01T17:00:00+00:00', 3, 3],
            ['2019-01-01T18:00:00+00:00', 4, 4],
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
            ['2019-01-01T15:00:00+00:00', 11, 11],
            ['2019-01-01T16:00:00+00:00', 21, 21],
            ['2019-01-01T17:00:00+00:00', 31, 31],
            ['2019-01-01T18:00:00+00:00', 41, 41],
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
            ['2019-01-01T15:00:00+00:00', 12, 12],
            ['2019-01-01T16:00:00+00:00', 22, 22],
            ['2019-01-01T17:00:00+00:00', 32, 32],
            ['2019-01-01T18:00:00+00:00', 42, 42],
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
            ['2019-01-01T15:00:00+00:00', 13, 13],
            ['2019-01-01T16:00:00+00:00', 23, 23],
            ['2019-01-01T17:00:00+00:00', 33, 33],
            ['2019-01-01T18:00:00+00:00', 43, 43],
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
            ['2019-01-01T15:00:00+00:00', 14, 14],
            ['2019-01-01T16:00:00+00:00', 24, 24],
            ['2019-01-01T17:00:00+00:00', 34, 34],
            ['2019-01-01T18:00:00+00:00', 44, 44],
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
            ['2019-01-01T15:00:00+00:00', 15, 15],
            ['2019-01-01T16:00:00+00:00', 25, 25],
            ['2019-01-01T17:00:00+00:00', 35, 35],
            ['2019-01-01T18:00:00+00:00', 45, 45],
        ];

        return new ChartResult($data);
    }

    public function fetchStats(
        string $publisherId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): DataCollection {
        $data = [
            new DataEntry(new Calculation(1, 1, 1, 1, 1, 1), '0F852769BC3E42E1A1D0DF420F9E794B', '6B30304F390A40A6B51CC2015250A7E1'),
            new DataEntry(new Calculation(2, 2, 2, 2, 2, 2), '0F852769BC3E42E1A1D0DF420F9E794B', '6B30304F390A40A6B51CC2015250A7E2'),
            new DataEntry(new Calculation(3, 3, 3, 3, 3, 3), '0F852769BC3E42E1A1D0DF420F9E794B', '6B30304F390A40A6B51CC2015250A7E3'),
            new DataEntry(new Calculation(4, 4, 4, 4, 4, 4), '0F852769BC3E42E1A1D0DF420F9E794B', '6B30304F390A40A6B51CC2015250A7E4'),
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
