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

use Adshares\Publisher\Dto\ChartInput;
use Adshares\Publisher\Dto\ChartResult;
use Adshares\Publisher\Repository\StatsRepository;

final class ChartDataProvider
{
    private const REPOSITORY_MAPPER = [
        ChartInput::CLICK_TYPE => 'fetchClick',
        ChartInput::VIEW_TYPE => 'fetchView',
        ChartInput::RPC_TYPE => 'fetchRpc',
        ChartInput::RPM_TYPE => 'fetchRpm',
        ChartInput::SUM_TYPE => 'fetchSum',
        ChartInput::CTR_TYPE => 'fetchCtr',
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
