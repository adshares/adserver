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

namespace Adshares\Adserver\Services\Advertiser\Dto;

use Illuminate\Contracts\Support\Arrayable;

class TargetingReach implements Arrayable
{
    /** @var float */
    private $occurrences = 0;

    /** @var float */
    private $cpm25 = 0;

    /** @var float */
    private $cpm50 = 0;

    /** @var float */
    private $cpm75 = 0;

    public function add(TargetingReachVector $vector, int $totalEventsCount): void
    {
        $additionalOccurrences = $vector->getOccurrencePercent() * $totalEventsCount;
        $totalOccurrences = $this->occurrences + $additionalOccurrences;

        if (0 === (int)$totalOccurrences) {
            return;
        }

        $this->cpm25 =
            ($this->occurrences * $this->cpm25 + $additionalOccurrences * $vector->getCpm25()) / $totalOccurrences;
        $this->cpm50 =
            ($this->occurrences * $this->cpm50 + $additionalOccurrences * $vector->getCpm50()) / $totalOccurrences;
        $this->cpm75 =
            ($this->occurrences * $this->cpm75 + $additionalOccurrences * $vector->getCpm75()) / $totalOccurrences;

        $this->occurrences = $totalOccurrences;
    }

    public function toArray(): array
    {
        return [
            'occurrences' => (int)round($this->occurrences),
            'cpm_percentiles' => [
                '25' => (int)round($this->cpm25 * 1e3),
                '50' => (int)round($this->cpm50 * 1e3),
                '75' => (int)round($this->cpm75 * 1e3),
            ],
        ];
    }
}
