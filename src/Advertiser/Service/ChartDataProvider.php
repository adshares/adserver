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

namespace Adshares\Advertiser\Service;

use Adshares\Advertiser\Dto\Input\ChartInput;
use Adshares\Advertiser\Repository\StatsRepository;
use Adshares\Advertiser\Dto\Result\ChartResult;

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
        StatsRepository::TYPE_CPC => 'fetchCpc',
        StatsRepository::TYPE_CPM => 'fetchCpm',
        StatsRepository::TYPE_SUM => 'fetchSum',
        StatsRepository::TYPE_SUM_BY_PAYMENT => 'fetchSumPayment',
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
            $input->getAdvertiserId(),
            $input->getResolution(),
            $input->getDateStart(),
            $input->getDateEnd(),
            $input->getCampaignId()
        );
    }
}
