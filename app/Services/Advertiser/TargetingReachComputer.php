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

namespace Adshares\Adserver\Services\Advertiser;

use Adshares\Adserver\Models\NetworkVectorsMeta;
use Adshares\Adserver\Services\Advertiser\Dto\TargetingReach;
use Adshares\Adserver\Services\Advertiser\Dto\TargetingReachVector;
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

        if (!$requires) {
            $keys[] = NetworkVectorComputer::TOTAL;
        }

        foreach ($requires as $category => $values) {
            foreach ($values as $value) {
                $keys[] = $category . ':' . $value;
            }
        }
        foreach ($excludes as $category => $values) {
            foreach ($values as $value) {
                $keys[] = $category . ':' . $value;
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
        )->where('network_host_id', $adServerId)->whereIn('key', $keys)->get()->keyBy('key');

        $vector = $this->computeRequiredVector($requires, $rows);

        if (null === $vector) {
            return null;
        }

        $exclusionsVector = $this->computeExclusionsVector($excludes, $rows);

        if (null !== $exclusionsVector) {
            $vector = $vector->and($exclusionsVector);
        }

        return $vector;
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
                $row = $rows->get($category . ':' . $value);

                if (!$row) {
                    continue;
                }

                $tmpVector = new TargetingReachVector(
                    $row->data,
                    $row->cpm_25,
                    $row->cpm_50,
                    $row->cpm_75
                );

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
                $row = $rows->get($category . ':' . $value);

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
}
