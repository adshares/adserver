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

namespace Adshares\Publisher\Dto\Result\Stats;

class Calculation
{
    /** @var int */
    private $clicks;

    /** @var int */
    private $impressions;

    /** @var float */
    private $ctr;

    /** @var int */
    private $averageRpc;

    /** @var int */
    private $averageRpm;

    /** @var int */
    private $revenue;

    /** @var string|null */
    private $domain;

    public function __construct(
        int $clicks,
        int $impressions,
        float $ctr,
        int $averageRpc,
        int $averageRpm,
        int $revenue,
        ?string $domain = null
    ) {
        $this->clicks = $clicks;
        $this->impressions = $impressions;
        $this->ctr = $ctr;
        $this->averageRpc = $averageRpc;
        $this->averageRpm = $averageRpm;
        $this->revenue = $revenue;
        $this->domain = $domain;
    }

    public function toArray(): array
    {
        $data = [
            'clicks' => $this->clicks,
            'impressions' => $this->impressions,
            'ctr' => $this->ctr,
            'averageRpc' => $this->averageRpc,
            'averageRpm' => $this->averageRpm,
            'revenue' => $this->revenue,
        ];

        if ($this->domain) {
            $data['domain'] = $this->domain;
        }

        return $data;
    }
}
