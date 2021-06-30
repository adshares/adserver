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

use Adshares\Adserver\Utilities\BinaryStringUtils;

class TargetingReachVector
{
    /** @var string */
    private $data;

    /** @var int */
    private $cpm25;

    /** @var int */
    private $cpm50;

    /** @var int */
    private $cpm75;

    public function __construct(string $data, int $cpm25, int $cpm50, int $cpm75)
    {
        $this->data = $data;
        $this->cpm25 = $cpm25;
        $this->cpm50 = $cpm50;
        $this->cpm75 = $cpm75;
    }

    public function and(self $vector): self
    {
        $andData = BinaryStringUtils::and($this->data, $vector->getData());

        $bitsA = BinaryStringUtils::count($this->data);
        $bitsB = BinaryStringUtils::count($vector->getData());
        $bitsAandB = BinaryStringUtils::count($andData);

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
        return new self(BinaryStringUtils::not($this->data), $this->cpm25, $this->cpm50, $this->cpm75);
    }

    public function or(self $vector): self
    {
        $andData = BinaryStringUtils::and($this->data, $vector->getData());
        $orData = BinaryStringUtils::or($this->data, $vector->getData());

        $bitsA = BinaryStringUtils::count($this->data);
        $bitsB = BinaryStringUtils::count($vector->getData());
        $bitsAandB = BinaryStringUtils::count($andData);

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
            (int)round(($weightThis * $this->cpm25 + $weightOther * $vectorOther->getCpm25()) / $weightsSum),
            (int)round(($weightThis * $this->cpm50 + $weightOther * $vectorOther->getCpm50()) / $weightsSum),
            (int)round(($weightThis * $this->cpm75 + $weightOther * $vectorOther->getCpm75()) / $weightsSum)
        );
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function getOccurrencePercent(): float
    {
        return BinaryStringUtils::count($this->data) / (8 * strlen($this->data));
    }

    public function getCpm25(): int
    {
        return $this->cpm25;
    }

    public function getCpm50(): int
    {
        return $this->cpm50;
    }

    public function getCpm75(): int
    {
        return $this->cpm75;
    }
}
