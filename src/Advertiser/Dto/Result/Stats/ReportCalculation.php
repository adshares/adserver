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

namespace Adshares\Advertiser\Dto\Result\Stats;

class ReportCalculation extends Calculation
{
    /** @var int */
    private $clicksAll;

    /** @var float */
    private $clicksInvalidRate;

    /** @var int */
    private $impressionsAll;

    /** @var float */
    private $impressionsInvalidRate;

    /** @var int */
    private $impressionsUnique;

    public function __construct(
        int $clicks,
        int $clicksAll,
        float $clicksInvalidRate,
        int $impressions,
        int $impressionsAll,
        float $impressionsInvalidRate,
        int $impressionsUnique,
        float $ctr,
        int $averageCpc,
        int $averageCpm,
        int $cost,
        ?string $domain = null
    ) {
        parent::__construct(
            $clicks,
            $impressions,
            $ctr,
            $averageCpc,
            $averageCpm,
            $cost,
            $domain
        );

        $this->clicksAll = $clicksAll;
        $this->clicksInvalidRate = $clicksInvalidRate;
        $this->impressionsUnique = $impressionsUnique;
        $this->impressionsAll = $impressionsAll;
        $this->impressionsInvalidRate = $impressionsInvalidRate;
    }

    public function toArray(): array
    {
        $data = [
            'clicksAll' => $this->clicksAll,
            'clicksInvalidRate' => $this->clicksInvalidRate,
            'impressionsAll' => $this->impressionsAll,
            'impressionsInvalidRate' => $this->impressionsInvalidRate,
            'impressionsUnique' => $this->impressionsUnique,
        ];

        return array_merge($data, parent::toArray());
    }
}
