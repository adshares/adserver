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

use Adshares\Advertiser\Dto\StatsInput;
use Adshares\Advertiser\Dto\StatsResult;
use Adshares\Advertiser\Repository\StatsRepository;

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
        return $this->repository->fetchStats(
            $input->getAdvertiserId(),
            $input->getDateStart(),
            $input->getDateEnd(),
            $input->getCampaignId()
        );
    }
}
