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

namespace Adshares\Advertiser\Dto;

class StatsEntry
{
    private $campaignId;

    /** @var int */
    private $clicks;

    /** @var int */
    private $impressions;

    /** @var float */
    private $ctr;

    /** @var float */
    private $averageCpc;

    /** @var int */
    private $cost;

    public function __construct(
        int $campaignId,
        int $clicks,
        int $impressions,
        float $ctr,
        float $averageCpc,
        int $cost
    ) {
        $this->campaignId = $campaignId;
        $this->clicks = $clicks;
        $this->impressions = $impressions;
        $this->ctr = $ctr;
        $this->averageCpc = $averageCpc;
        $this->cost = $cost;
    }

    public function toArray(): array
    {
        return [
            'campaignId' => $this->campaignId,
            'clicks' => $this->clicks,
            'impressions' => $this->impressions,
            'ctr' => $this->ctr,
            'averageCpc' => $this->averageCpc,
            'cost' => $this->cost,
        ];
    }
}
