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

namespace Adshares\Adserver\Http\Requests\Campaign;

class TargetingProcessor
{
    /** @var array */
    private $targetingSchema;

    public function __construct(array $targetingSchema)
    {
        $this->targetingSchema = $targetingSchema;
    }

    public function processTargeting(?array $targeting): array
    {
        if (!$targeting) {
            return [];
        }

        return $this->processGroups($targeting, $this->targetingSchema);
    }

    private function processGroups(array $groups, array $schema): array
    {
        $groupsProcessed = [];

        foreach ($groups as $key => $group) {
            $shouldBeAdded = false;

            foreach ($schema as $availableGroup) {
                if ($key !== $availableGroup['key']) {
                    continue;
                }

                if ('group' === $availableGroup['value_type']) {
                    $processed = $this->processGroups($group, $availableGroup['children']);
                    $shouldBeAdded = true;
                } elseif ('string' === $availableGroup['value_type']) {
                    if ($availableGroup['allow_input']) {
                        $processed = $this->processInputs($group);
                    } else {
                        $processed = $this->processValues($group, $availableGroup['values']);
                    }

                    if (count($processed) > 0) {
                        $shouldBeAdded = true;
                    }
                }

                break;
            }

            if ($shouldBeAdded) {
                $groupsProcessed[$key] = $processed;
            }
        }

        return $groupsProcessed;
    }

    private function processValues(array $values, array $schema): array
    {
        $valuesProcessed = [];

        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }

            $shouldBeAdded = false;

            foreach ($schema as $availableValue) {
                if ($value === $availableValue['value']) {
                    $shouldBeAdded = true;

                    break;
                }
            }

            if ($shouldBeAdded) {
                $valuesProcessed[] = $value;
            }
        }

        return $valuesProcessed;
    }

    private function processInputs(array $inputs): array
    {
        $inputsProcessed = [];

        foreach ($inputs as $value) {
            if (is_string($value)) {
                $inputsProcessed[] = $value;
            }
        }

        return $inputsProcessed;
    }
}
