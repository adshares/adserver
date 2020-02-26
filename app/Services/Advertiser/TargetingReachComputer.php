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

namespace Adshares\Adserver\Services\Advertiser;

use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\NetworkVectorsMeta;
use Adshares\Adserver\Services\Advertiser\Dto\TargetingReach;
use Adshares\Adserver\Services\Advertiser\Dto\TargetingReachVector;
use Adshares\Adserver\Utilities\BinaryStringUtils;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TargetingReachComputer
{
    public function compute(array $requires, array $excludes): TargetingReach
    {
        $networkVectorsMetas = NetworkVectorsMeta::fetch();
        $targetingReach = new TargetingReach();

        /** @var NetworkVectorsMeta $networkVectorsMeta */
        foreach ($networkVectorsMetas as $networkVectorsMeta) {
            $record = $this->computeForServer($networkVectorsMeta->network_host_id, $requires, $excludes);

            if (null === $record) {
                continue;
            }

            $targetingReach->add($record, $networkVectorsMeta->total_events_count);
        }

        return $targetingReach;
    }

    private function computeForServer(int $adServerId, array $requires, array $excludes): ?TargetingReachVector
    {
        $keys = [];
        $siteRequired = [];
        $siteExcluded = [];

        if (isset($requires['site:domain'])) {
            $siteRequired = self::escapeArrayForPregMatch($requires['site:domain']);
            unset($requires['site:domain']);
        }

        if (!$requires) {
            $keys[] = NetworkVectorComputer::TOTAL;
        }

        foreach ($requires as $category => $values) {
            foreach ($values as $value) {
                $keys[] = $category.':'.$value;
            }
        }

        if (isset($excludes['site:domain'])) {
            $siteExcluded = self::escapeArrayForPregMatch($excludes['site:domain']);
            unset($excludes['site:domain']);
        }

        foreach ($excludes as $category => $values) {
            foreach ($values as $value) {
                $keys[] = $category.':'.$value;
            }
        }

        $rows = DB::table('network_vectors')->select(
            [
                'key',
                'cpm_25',
                'cpm_50',
                'cpm_75',
                'negation_cpm_25',
                'negation_cpm_50',
                'negation_cpm_75',
                'data',
            ]
        )->where('network_host_id', $adServerId)->where(
            function ($query) use ($keys) {
                $query->whereIn('key', $keys)->orWhere('key', 'like', 'site:domain:%');
            }
        )->get()->keyBy('key');

        if (count($siteRequired) > 0) {
            $requiredRegExp = self::getRegularExpressionForSiteDomains($siteRequired);
            foreach ($rows as $key => $row) {
                if (1 === preg_match($requiredRegExp, $key)) {
                    $requires['site:domain'][] = substr($key, 12);
                }
            }

            if (!isset($requires['site:domain'])) {
                return null;
            }
        }

        $vector = $this->computeRequiredVector($requires, $rows);

        if (null === $vector) {
            return null;
        }

        if (count($siteExcluded) > 0) {
            $excludedRegExp = self::getRegularExpressionForSiteDomains($siteExcluded);
            foreach ($rows as $key => $row) {
                if (1 === preg_match($excludedRegExp, $key)) {
                    $excludes['site:domain'][] = substr($key, 12);
                }
            }
        }

        $exclusionsVector = $this->computeExclusionsVector($excludes, $rows);

        if (null !== $exclusionsVector) {
            $vector = $vector->and($exclusionsVector);
        }

        return $this->useBidStrategyRank($vector, $rows);
    }

    private function computeRequiredVector(array $requires, Collection $rows): ?TargetingReachVector
    {
        if (!$requires) {
            $row = $rows->get(NetworkVectorComputer::TOTAL);

            if (!$row) {
                return null;
            }

            return new TargetingReachVector($row->data, $row->cpm_25, $row->cpm_50, $row->cpm_75);
        }

        /** @var TargetingReachVector|null $vector */
        $vector = null;

        foreach ($requires as $category => $values) {
            /** @var TargetingReachVector|null $orVector */
            $orVector = null;
            foreach ($values as $value) {
                $row = $rows->get($category.':'.$value);

                if (!$row) {
                    continue;
                }

                $tmpVector = new TargetingReachVector($row->data, $row->cpm_25, $row->cpm_50, $row->cpm_75);

                if (null === $orVector) {
                    $orVector = $tmpVector;
                } else {
                    $orVector = $orVector->or($tmpVector);
                }
            }

            if (null === $orVector) {
                $vector = null;

                break;
            }

            if (null === $vector) {
                $vector = $orVector;
            } else {
                $vector = $vector->and($orVector);
            }
        }

        return $vector;
    }

    private function computeExclusionsVector(array $excludes, Collection $rows): ?TargetingReachVector
    {
        /** @var TargetingReachVector|null $vector */
        $vector = null;

        foreach ($excludes as $category => $values) {
            foreach ($values as $value) {
                $row = $rows->get($category.':'.$value);

                if (!$row) {
                    continue;
                }

                $tmpVector = new TargetingReachVector(
                    $row->data,
                    $row->negation_cpm_25,
                    $row->negation_cpm_50,
                    $row->negation_cpm_75
                );

                if (null === $vector) {
                    $vector = $tmpVector;
                } else {
                    $vector = $vector->or($tmpVector);
                }
            }
        }

        if (null !== $vector) {
            $vector = $vector->not();
        }

        return $vector;
    }

    private static function escapeArrayForPregMatch(array $array): array
    {
        return array_map(
            function ($item) {
                return preg_quote($item);
            },
            $array
        );
    }

    private static function getRegularExpressionForSiteDomains(array $siteDomains): string
    {
        return '/^site:domain:(.+\.)?('.join('|', $siteDomains).')$/i';
    }

    private static function useBidStrategyRank(TargetingReachVector $vector, Collection $rows): TargetingReachVector
    {
        $vectorData = $vector->getData();
        $vectorCpm25 = $vector->getCpm25();
        $vectorCpm50 = $vector->getCpm50();
        $vectorCpm75 = $vector->getCpm75();

        $countProcessed = 0;
        $cpm25 = 0;
        $cpm50 = 0;
        $cpm75 = 0;

        $totalCount = BinaryStringUtils::count($vectorData);
        $bidStrategies = BidStrategy::fetchAllWithReducedRank();
        /** @var BidStrategy $bidStrategy */
        foreach ($bidStrategies as $bidStrategy) {
            if ($countProcessed >= $totalCount) {
                break;
            }

            $row = $rows->get($bidStrategy->category);

            if (null === $row) {
                continue;
            }

            $count = BinaryStringUtils::count(BinaryStringUtils::and($vectorData, $row->data));

            if (0 === $count) {
                continue;
            }

            $rank = $bidStrategy->rank;
            if ($rank < 0.001) {
                $vectorData = BinaryStringUtils::and($vectorData, BinaryStringUtils::not($row->data));
                $totalCount -= $count;

                continue;
            }

            $countProcessedTmp = $countProcessed + $count;

            $cpm25 = ($countProcessed * $cpm25 + $count * ($vectorCpm25 / $rank)) / $countProcessedTmp;
            $cpm50 = ($countProcessed * $cpm50 + $count * ($vectorCpm50 / $rank)) / $countProcessedTmp;
            $cpm75 = ($countProcessed * $cpm75 + $count * ($vectorCpm75 / $rank)) / $countProcessedTmp;

            $countProcessed = $countProcessedTmp;
        }

        if ($totalCount > $countProcessed) {
            $count = $totalCount - $countProcessed;
            $cpm25 = ($countProcessed * $cpm25 + $count * $vectorCpm25) / $totalCount;
            $cpm50 = ($countProcessed * $cpm50 + $count * $vectorCpm50) / $totalCount;
            $cpm75 = ($countProcessed * $cpm75 + $count * $vectorCpm75) / $totalCount;
        }

        return new TargetingReachVector($vectorData, (int)$cpm25, (int)$cpm50, (int)$cpm75);
    }
}
