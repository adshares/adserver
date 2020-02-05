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

use Adshares\Adserver\Utilities\BinaryStringUtils;

class TargetingReachVector
{
    /** @var string */
    private $data;

    /** @var int */
    private $percentile25;

    /** @var int */
    private $percentile50;

    /** @var int */
    private $percentile75;

    public function __construct(string $data, int $percentile25, int $percentile50, int $percentile75)
    {
        $this->data = $data;
        $this->percentile25 = $percentile25;
        $this->percentile50 = $percentile50;
        $this->percentile75 = $percentile75;
    }

    public function and(self $vector): self
    {
        $andData = (string)($this->data & $vector->getData());

        $bitsA = BinaryStringUtils::countSetBitsInBinaryString($this->data);
        $bitsB = BinaryStringUtils::countSetBitsInBinaryString($vector->getData());
        $bitsAandB = BinaryStringUtils::countSetBitsInBinaryString($andData);

        if ($bitsA === $bitsAandB && $bitsB === $bitsAandB) {
            $xA = 1;
            $xB = 1;
        } elseif ($bitsA === $bitsAandB) {
            $xA = 1;
            $xB = 0;
        } elseif ($bitsB === $bitsAandB) {
            $xA = 0;
            $xB = 1;
        } else {
            $xA = $bitsA / ($bitsA - $bitsAandB);
            $xB = $bitsB / ($bitsB - $bitsAandB);
        }

        return $this->createWeightedVector($andData, $xA, $xB, $vector);
    }

    public function not(): self
    {
        return new self((string)~$this->data, $this->percentile25, $this->percentile50, $this->percentile75);
    }

    public function or(self $vector): self
    {
        $andData = (string)($this->data & $vector->getData());
        $orData = (string)($this->data | $vector->getData());

        $bitsA = BinaryStringUtils::countSetBitsInBinaryString($this->data);
        $bitsB = BinaryStringUtils::countSetBitsInBinaryString($vector->getData());
        $bitsAandB = BinaryStringUtils::countSetBitsInBinaryString($andData);

        if ($bitsB === $bitsAandB && $bitsA === $bitsAandB) {
            $yA = 1;
            $yB = 1;
        } elseif ($bitsB === $bitsAandB) {
            $yA = 1;
            $yB = 0;
        } elseif ($bitsA === $bitsAandB) {
            $yA = 0;
            $yB = 1;
        } else {
            $yA = $bitsA / ($bitsB - $bitsAandB);
            $yB = $bitsB / ($bitsA - $bitsAandB);
        }

        return $this->createWeightedVector($orData, $yA, $yB, $vector);
    }

    private function createWeightedVector(
        string $data,
        float $weightThis,
        float $weightOther,
        TargetingReachVector $vectorOther
    ): self {
        $weightsSum = $weightThis + $weightOther;

        return new self(
            $data,
            (int)round(
                ($weightThis * $this->percentile25 + $weightOther * $vectorOther->getPercentile25()) / $weightsSum
            ),
            (int)round(
                ($weightThis * $this->percentile50 + $weightOther * $vectorOther->getPercentile50()) / $weightsSum
            ),
            (int)round(
                ($weightThis * $this->percentile75 + $weightOther * $vectorOther->getPercentile75()) / $weightsSum
            )
        );
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getOccurrencePercent(): float
    {
        return BinaryStringUtils::countSetBitsInBinaryString($this->data) / (8 * strlen($this->data));
    }

    public function getPercentile25(): int
    {
        return $this->percentile25;
    }

    public function getPercentile50(): int
    {
        return $this->percentile50;
    }

    public function getPercentile75(): int
    {
        return $this->percentile75;
    }
}
