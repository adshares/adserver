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

namespace Adshares\Adserver\Utilities;

class Bucketer
{
    private $totalBuckets = 0;

    /** @var int */
    private $maxBuckets;

    /** @var mixed */
    private $min;

    /** @var mixed */
    private $max;

    /** @var int */
    private $totalCount = 0;

    /** @var array */
    private $buckets;

    public function __construct(int $maxBuckets = 64)
    {
        $this->maxBuckets = $maxBuckets;
    }

    public function add($value): void
    {
        ++$this->totalCount;

        if (null === $this->buckets) {
            $this->buckets = [[$value, $value, 1]];
            $this->min = $this->max = $value;
            $this->totalBuckets = 1;
        } else {
            if ($value < $this->min) {
                $this->min = $value;
                array_unshift($this->buckets, [$value, $value, 1]);
                $this->totalBuckets++;
            } elseif ($value > $this->max) {
                $this->max = $value;
                array_push($this->buckets, [$value, $value, 1]);
                $this->totalBuckets++;
            } elseif ($value === $this->min) {
                ++$this->buckets[0][2];
            } elseif ($value === $this->max) {
                ++$this->buckets[$this->totalBuckets - 1][2];
            } else {
                $this->insertBucket($value);
            }
        }

        if ($this->totalBuckets > $this->maxBuckets) {
            $this->mergeBuckets();
        }
    }

    private function insertBucket($value): void
    {
        $left = 0;
        $right = $this->totalBuckets - 1;

        while ($right - $left > 0) {
            $position = (int)(($right + $left) / 2);
            $item = $this->buckets[$position];
            if ($value < $item[0]) {
                $right = $position - 1;
            } elseif ($value > $item[1]) {
                $left = $position + 1;
            } else {
                ++$this->buckets[$position][2];

                return;
            }
        }

        $position = $left;
        $item = $this->buckets[$position];
        if ($value < $item[0]) {
            array_splice($this->buckets, $position, 0, [[$value, $value, 1]]);
            ++$this->totalBuckets;
        } elseif ($value > $item[1]) {
            array_splice($this->buckets, $position + 1, 0, [[$value, $value, 1]]);
            ++$this->totalBuckets;
        } else {
            ++$this->buckets[$position][2];
        }
    }

    private function mergeBuckets(): void
    {
        $minPosition = 0;
        $minCount = $this->buckets[$minPosition][2];
        $mergePosition = 1;
        $mergeCount = $this->buckets[$mergePosition][2];

        for ($position = 1; $position < $this->totalBuckets; ++$position) {
            $currentCount = $this->buckets[$position][2];
            if ($position === $this->totalBuckets - 1) {
                $currentMergePosition = $position - 1;
            } else {
                $currentMergePosition =
                    $this->buckets[$position + 1][2] < $this->buckets[$position - 1][2] ? $position + 1 : $position - 1;
            }
            $currentMergeCount = $this->buckets[$currentMergePosition][2];
            if ($currentCount < $minCount || ($currentCount === $minCount && $currentMergeCount < $mergeCount)) {
                $minPosition = $position;
                $minCount = $currentCount;
                $mergePosition = $currentMergePosition;
                $mergeCount = $currentMergeCount;
            }
        }

        if ($mergePosition > $minPosition) {
            $this->buckets[$minPosition][1] = $this->buckets[$mergePosition][1];
        } else {
            $this->buckets[$minPosition][0] = $this->buckets[$mergePosition][0];
        }
        $this->buckets[$minPosition][2] += $this->buckets[$mergePosition][2];
        unset($this->buckets[$mergePosition]);
        $this->buckets = array_values($this->buckets);
        $this->totalBuckets--;
    }

    public function percentile($percentile = 0.5)
    {
        $currentCount = 0;
        $percentileCount = $this->totalCount * $percentile;
        $bucket = [0, 0, 0];
        for ($i = 0; $i < $this->totalBuckets; ++$i) {
            $bucket = $this->buckets[$i];
            $currentCount += $bucket[2];
            if ($currentCount >= $percentileCount) {
                return $bucket[1];
            }
        }

        return $bucket[1];
    }

    public function percentiles(array $percentiles = [0.25, 0.5, 0.75]): array
    {
        $count = count($percentiles);

        if (0 === $count) {
            return [];
        }

        sort($percentiles);

        $index = 0;
        $result = [];

        $currentCount = 0;
        $percentileCount = $this->totalCount * $percentiles[$index];
        $bucket = [0, 0, 0];
        for ($i = 0; $i < $this->totalBuckets; ++$i) {
            $bucket = $this->buckets[$i];
            $currentCount += $bucket[2];
            while ($currentCount >= $percentileCount) {
                $result[] = $bucket[1];
                $index++;

                if ($count <= $index) {
                    return $result;
                }

                $percentileCount = $this->totalCount * $percentiles[$index];
            }
        }

        while ($count > $index) {
            $result[] = $bucket[1];
            $index++;
        }

        return $result;
    }
}
