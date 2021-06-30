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

namespace Adshares\Adserver\Utilities;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class PercentileComputer
{
    /** @var int */
    private $totalCount = 0;

    private const KEY_COUNT = 'count';

    private const KEY_AVERAGE = 'average';

    /** @var array */
    private $campaigns;

    public function __construct()
    {
        $this->campaigns = [];
    }

    public function add(int $campaignId, int $value): void
    {
        ++$this->totalCount;

        if (!isset($this->campaigns[$campaignId])) {
            $this->campaigns[$campaignId] = [
                self::KEY_COUNT => 1,
                self::KEY_AVERAGE => $value,
            ];
        } else {
            $count = ++$this->campaigns[$campaignId][self::KEY_COUNT];

            $this->campaigns[$campaignId][self::KEY_AVERAGE] =
                ($this->campaigns[$campaignId][self::KEY_AVERAGE] * ($count - 1) + $value) / $count;
        }
    }

    public function percentiles(array $percentileRanks = [25, 50, 75]): array
    {
        if (0 === count($percentileRanks) || !$this->campaigns) {
            return [];
        }

        sort($percentileRanks);

        $cutOff = (int)((1 - $percentileRanks[0] / 100) * $this->totalCount);
        $cutArray = $this->processCampaigns($cutOff);
        $cutArrayItemCount = count($cutArray);

        $predecessorIndices = array_fill(0, $cutArrayItemCount, 0);
        $tailIndices = array_fill(0, $cutArrayItemCount + 1, 0);
        $longestSubsequenceLength = 0;
        for ($index = 0; $index < $cutArrayItemCount; $index++) {
            $lo = 1;
            $hi = $longestSubsequenceLength;
            while ($lo <= $hi) {
                $mid = (int)ceil(($lo + $hi) / 2);
                if ($cutArray[$tailIndices[$mid]][self::KEY_AVERAGE] > $cutArray[$index][self::KEY_AVERAGE]) {
                    $lo = $mid + 1;
                } else {
                    $hi = $mid - 1;
                }
            }

            $newLength = $lo;

            $predecessorIndices[$index] = $tailIndices[$newLength - 1];
            $tailIndices[$newLength] = $index;

            if ($newLength > $longestSubsequenceLength) {
                $longestSubsequenceLength = $newLength;
            }
        }

        $k = $tailIndices[$longestSubsequenceLength];
        if ($k < $cutArrayItemCount - 1) {
            $targetAverage = $cutArray[$k][self::KEY_AVERAGE];
            $i = $cutArrayItemCount - 1;
            while ($i > $k) {
                $cutArray[$i][self::KEY_AVERAGE] = $targetAverage;
                $i--;
            }
        }

        for ($index = $longestSubsequenceLength - 1; $index >= 0; $index--) {
            $nextK = $predecessorIndices[$k];

            if ($k > $nextK + 1) {
                $gap = $k - $nextK;
                $x1 = $cutArray[$k][self::KEY_COUNT];
                $y1 = $cutArray[$k][self::KEY_AVERAGE];
                $x2 = $cutArray[$nextK][self::KEY_COUNT];
                $y2 = $cutArray[$nextK][self::KEY_AVERAGE];

                if ($x2 === $x1) {
                    $averageY = ($y2 + $y1) / 2;
                    for ($i = 1; $i < $gap; $i++) {
                        $cutArray[$k - $i][self::KEY_AVERAGE] = $averageY;
                    }
                } else {
                    $a = ($y2 - $y1) / ($x2 - $x1);
                    $b = $y2 - $a * $x2;

                    for ($i = 1; $i < $gap; $i++) {
                        $cutArray[$k - $i][self::KEY_AVERAGE] = $a * $cutArray[$k - $i][self::KEY_COUNT] + $b;
                    }
                }
            }

            if (0 === $index && $k > 0) {
                $targetAverage = $cutArray[$k][self::KEY_AVERAGE];
                while ($k > 0) {
                    $k--;
                    $cutArray[$k][self::KEY_AVERAGE] = $targetAverage;
                }
            }

            $k = $nextK;
        }

        return $this->getPercentilesFromCutArray($cutArray, $percentileRanks);
    }

    private function mergeDuplicatedAverages(array $arr): array
    {
        $lastAverage = -1;
        $indexesToDelete = [];

        foreach ($arr as $index => $entry) {
            if ($entry[self::KEY_AVERAGE] === $lastAverage) {
                $indexesToDelete[] = $index;
            }

            $lastAverage = $entry[self::KEY_AVERAGE];
        }

        if ($indexesToDelete) {
            foreach (array_reverse($indexesToDelete) as $index) {
                $arr[$index - 1][self::KEY_COUNT] += $arr[$index][self::KEY_COUNT];
                unset($arr[$index]);
            }

            $arr = array_values($arr);
        }

        return $arr;
    }

    private function processCampaigns(int $cutOff): array
    {
        $tmp = $this->campaigns;
        usort(
            $tmp,
            function ($a, $b) {
                if ($a[self::KEY_COUNT] == $b[self::KEY_COUNT]) {
                    if ($a[self::KEY_AVERAGE] == $b[self::KEY_AVERAGE]) {
                        return 0;
                    }

                    return $a[self::KEY_AVERAGE] > $b[self::KEY_AVERAGE] ? -1 : 1;
                }

                return $a[self::KEY_COUNT] > $b[self::KEY_COUNT] ? -1 : 1;
            }
        );

        $currentCount = 0;
        $index = 0;
        foreach ($tmp as $index => $entry) {
            if (($currentCount += $entry[self::KEY_COUNT]) > $cutOff) {
                break;
            }
        }

        return $this->mergeDuplicatedAverages(array_slice($tmp, 0, $index + 1));
    }

    private function getPercentilesFromCutArray(array $cutArray, array $percentileRanks): array
    {
        $cutOff = array_reduce(
            $cutArray,
            function ($carry, $item) {
                return $carry + $item[self::KEY_COUNT];
            },
            0
        );

        $result = [];
        $percentilesIndex = 0;
        $percentilesArrayCount = count($percentileRanks);
        $currentCount = $this->totalCount - $cutOff;
        $percentileCount = $this->totalCount * $percentileRanks[$percentilesIndex] / 100;

        for ($i = count($cutArray) - 1; $i >= 0; $i--) {
            $currentCount += $cutArray[$i][self::KEY_COUNT];
            while ($currentCount >= $percentileCount) {
                $result[] = $cutArray[$i][self::KEY_AVERAGE];
                $percentilesIndex++;

                if ($percentilesArrayCount <= $percentilesIndex) {
                    return $result;
                }

                $percentileCount = $this->totalCount * $percentileRanks[$percentilesIndex] / 100;
            }
        }

        while ($percentilesArrayCount > $percentilesIndex) {
            $result[] = $cutArray[0][self::KEY_AVERAGE];
            $percentilesIndex++;
        }

        return $result;
    }
}
