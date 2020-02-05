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

namespace Adshares\Adserver\Services\Advertiser\Dto;

use Illuminate\Contracts\Support\Arrayable;

class TargetingReach implements Arrayable
{
    /** @var float */
    private $occurrences = 0;

    /** @var float */
    private $percentile25 = 0;

    /** @var float */
    private $percentile50 = 0;

    /** @var float */
    private $percentile75 = 0;

    public function add(TargetingReachVector $vector, int $totalEventsCount): void
    {
        $additionalOccurrences = $vector->getOccurrencePercent() * $totalEventsCount;
        $totalOccurrences = $this->occurrences + $additionalOccurrences;

        if (0 === (int)$totalOccurrences) {
            return;
        }

        $this->percentile25 =
            ($this->occurrences * $this->percentile25 + $additionalOccurrences * $vector->getPercentile25())
            / $totalOccurrences;
        $this->percentile50 =
            ($this->occurrences * $this->percentile50 + $additionalOccurrences * $vector->getPercentile50())
            / $totalOccurrences;
        $this->percentile75 =
            ($this->occurrences * $this->percentile75 + $additionalOccurrences * $vector->getPercentile75())
            / $totalOccurrences;

        $this->occurrences = $totalOccurrences;
    }

    public function toArray(): array
    {
        return [
            'occurrences' => (int)round($this->occurrences),
            'percentiles' => [
                '25' => (int)round($this->percentile25 * 1e3),
                '50' => (int)round($this->percentile50 * 1e3),
                '75' => (int)round($this->percentile75 * 1e3),
            ],
        ];
    }
}
