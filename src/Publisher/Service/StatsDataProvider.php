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

namespace Adshares\Publisher\Service;

use Adshares\Publisher\Dto\Input\StatsInput;
use Adshares\Publisher\Dto\Result\Stats\DataCollection;
use Adshares\Publisher\Dto\Result\StatsResult;
use Adshares\Publisher\Repository\StatsRepository;

class StatsDataProvider
{
    /** @var StatsRepository */
    private $repository;

    public function __construct(StatsRepository $repository)
    {
        $this->repository = $repository;
    }

    public function fetch(StatsInput $input): StatsResult
    {
        $total = $this->repository->fetchStatsTotal(
            $input->getPublisherId(),
            $input->getDateStart(),
            $input->getDateEnd(),
            $input->getSiteId()
        );

        $data = $this->repository->fetchStats(
            $input->getPublisherId(),
            $input->getDateStart(),
            $input->getDateEnd(),
            $input->getSiteId()
        );

        return new StatsResult($total, $data);
    }

    public function fetchReportData(StatsInput $input): DataCollection
    {
        return $this->repository->fetchStatsToReport(
            $input->getPublisherId(),
            $input->getDateStart(),
            $input->getDateEnd(),
            $input->getSiteId()
        );
    }
}
