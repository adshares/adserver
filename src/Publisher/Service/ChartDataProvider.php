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

namespace Adshares\Publisher\Service;

use Adshares\Publisher\Dto\Input\ChartInput;
use Adshares\Publisher\Dto\Result\ChartResult;
use Adshares\Publisher\Repository\StatsRepository;

final class ChartDataProvider
{
    private const REPOSITORY_MAPPER = [
        StatsRepository::CLICK_TYPE => 'fetchClick',
        StatsRepository::CLICK_ALL_TYPE => 'fetchClickAll',
        StatsRepository::CLICK_INVALID_RATE_TYPE => 'fetchClickInvalidRate',
        StatsRepository::VIEW_TYPE => 'fetchView',
        StatsRepository::VIEW_ALL_TYPE => 'fetchViewAll',
        StatsRepository::VIEW_INVALID_RATE_TYPE => 'fetchViewInvalidRate',
        StatsRepository::RPC_TYPE => 'fetchRpc',
        StatsRepository::RPM_TYPE => 'fetchRpm',
        StatsRepository::SUM_TYPE => 'fetchSum',
        StatsRepository::CTR_TYPE => 'fetchCtr',
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
