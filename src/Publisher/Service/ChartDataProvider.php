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

use Adshares\Publisher\Dto\Input\ChartInput;
use Adshares\Publisher\Dto\Result\ChartResult;
use Adshares\Publisher\Repository\StatsRepository;

final class ChartDataProvider
{
    private const REPOSITORY_MAPPER = [
        StatsRepository::TYPE_CLICK => 'fetchClick',
        StatsRepository::TYPE_CLICK_ALL => 'fetchClickAll',
        StatsRepository::TYPE_CLICK_INVALID_RATE => 'fetchClickInvalidRate',
        StatsRepository::TYPE_VIEW => 'fetchView',
        StatsRepository::TYPE_VIEW_UNIQUE => 'fetchViewUnique',
        StatsRepository::TYPE_VIEW_ALL => 'fetchViewAll',
        StatsRepository::TYPE_VIEW_INVALID_RATE => 'fetchViewInvalidRate',
        StatsRepository::TYPE_RPC => 'fetchRpc',
        StatsRepository::TYPE_RPM => 'fetchRpm',
        StatsRepository::TYPE_REVENUE_BY_CASE => 'fetchSum',
        StatsRepository::TYPE_REVENUE_BY_HOUR => 'fetchSumHour',
        StatsRepository::TYPE_CTR => 'fetchCtr',
    ];

    /** @var StatsRepository */
    private $repository;

    public function __construct(StatsRepository $repository)
    {
        $this->repository = $repository;
    }

    public function fetch(ChartInput $input): ChartResult
    {
        $method = self::REPOSITORY_MAPPER[$input->getType()];

        return $this->repository->$method(
            $input->getPublisherId(),
            $input->getResolution(),
            $input->getDateStart(),
            $input->getDateEnd(),
            $input->getSiteId()
        );
    }
}
