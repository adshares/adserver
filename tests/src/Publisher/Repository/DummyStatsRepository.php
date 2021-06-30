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
    public const USER_EMAIL = 'postman@dev.dev';

    public function fetchView(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
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
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $this->fetchView($publisherId, $resolution, $dateStart, $dateEnd, $siteId);
    }

    public function fetchViewInvalidRate(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
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
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $this->fetchView($publisherId, $resolution, $dateStart, $dateEnd, $siteId);
    }

    public function fetchClick(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
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
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $this->fetchClick($publisherId, $resolution, $dateStart, $dateEnd, $siteId);
    }

    public function fetchClickInvalidRate(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $data = [
            ['2019-01-01T15:00:00+00:00', 0.02],
            ['2019-01-01T16:00:00+00:00', 0.32],
            ['2019-01-01T17:00:00+00:00', 0.62],
            ['2019-01-01T18:00:00+00:00', 0.92],
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
            ['2019-01-01T15:00:00+00:00', 12],
            ['2019-01-01T16:00:00+00:00', 22],
            ['2019-01-01T17:00:00+00:00', 32],
            ['2019-01-01T18:00:00+00:00', 42],
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
            ['2019-01-01T15:00:00+00:00', 13],
            ['2019-01-01T16:00:00+00:00', 23],
            ['2019-01-01T17:00:00+00:00', 33],
            ['2019-01-01T18:00:00+00:00', 43],
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
            ['2019-01-01T15:00:00+00:00', 14],
            ['2019-01-01T16:00:00+00:00', 24],
            ['2019-01-01T17:00:00+00:00', 34],
            ['2019-01-01T18:00:00+00:00', 44],
        ];

        return new ChartResult($data);
    }

    public function fetchSumHour(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $data = [
            ['2019-01-01T15:00:00+00:00', 15],
            ['2019-01-01T16:00:00+00:00', 25],
            ['2019-01-01T17:00:00+00:00', 35],
            ['2019-01-01T18:00:00+00:00', 45],
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
            ['2019-01-01T15:00:00+00:00', 0.03],
            ['2019-01-01T16:00:00+00:00', 0.33],
            ['2019-01-01T17:00:00+00:00', 0.63],
            ['2019-01-01T18:00:00+00:00', 0.93],
        ];

        return new ChartResult($data);
    }

    public function fetchStats(
        ?string $publisherId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): DataCollection {
        $data = [
            new DataEntry(
                new Calculation(1, 1, 1, 1, 1, 1),
                1,
                'site',
                1,
                'zone1'
            ),
            new DataEntry(
                new Calculation(2, 2, 2, 2, 2, 2),
                1,
                'site',
                2,
                'zone2'
            ),
            new DataEntry(
                new Calculation(3, 3, 3, 3, 3, 3),
                1,
                'site',
                3,
                'zone3'
            ),
            new DataEntry(
                new Calculation(4, 4, 4, 4, 4, 4),
                1,
                'site',
                4,
                'zone4'
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
        ?string $publisherId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): DataCollection {
        // TODO: Implement fetchStatsToReport() method.
    }
}
