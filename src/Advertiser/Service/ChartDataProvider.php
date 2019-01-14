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

namespace Adshares\Advertiser\Service;

use Adshares\Advertiser\Dto\ChartInput;
use Adshares\Advertiser\Repository\StatsRepository;
use Adshares\Advertiser\Dto\ChartResult;

final class ChartDataProvider
{
    private const REPOSITORY_MAPPER = [
        ChartInput::CLICK_TYPE => 'fetchClick',
        ChartInput::VIEW_TYPE => 'fetchView',
        ChartInput::CPC_TYPE => 'fetchCpc',
        ChartInput::CPM_TYPE => 'fetchCpm',
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
            $input->getAdvertiserId(),
            $input->getResolution(),
            $input->getDateStart(),
            $input->getDateEnd(),
            $input->getCampaignId(),
            $input->getBannerId()
        );
    }
}
