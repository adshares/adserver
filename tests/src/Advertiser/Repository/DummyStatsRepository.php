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

use Adshares\Advertiser\Repository\StatsRepository;
use Adshares\Advertiser\Service\ChartResult;
use DateTime;

class DummyStatsRepository implements StatsRepository
{

    public function fetchView(
        int $advertiser,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?int $campaignId = null,
        ?int $bannerId = null
    ): ChartResult {
        $data = [
            [1, 22, 32],
            [2, 26, 1],
            [3, 37, 1],
            [4, 12, 2],
        ];

        return new ChartResult($data);
    }

    public function fetchClick(
        int $advertiser,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?int $campaignId = null,
        ?int $bannerId = null
    ): ChartResult {
        // TODO: Implement fetchClick() method.
    }

    public function fetchCpc(
        int $advertiser,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?int $campaignId = null,
        ?int $bannerId = null
    ): ChartResult {
        // TODO: Implement fetchCpc() method.
    }

    public function fetchCpm(
        int $advertiser,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?int $campaignId = null,
        ?int $bannerId = null
    ): ChartResult {
        // TODO: Implement fetchCpm() method.
    }

    public function fetchSum(
        int $advertiser,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?int $campaignId = null,
        ?int $bannerId = null
    ): ChartResult {
        // TODO: Implement fetchSum() method.
    }

    public function fetchCtr(
        int $advertiser,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?int $campaignId = null,
        ?int $bannerId = null
    ): ChartResult {
        // TODO: Implement fetchCtr() method.
    }
}
