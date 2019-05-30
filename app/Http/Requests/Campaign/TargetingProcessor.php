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

use Adshares\Adserver\ViewModel\OptionsSelector;
use Adshares\Common\Application\Model\Selector;

class TargetingProcessor
{
    /** @var array */
    private $targetingSchema;

    public function __construct(Selector $targetingSchema)
    {
        $this->targetingSchema = (new OptionsSelector($targetingSchema))->toArray();
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
            if (!is_array($group)) {
                continue;
            }

            foreach ($schema as $availableGroup) {
                if ($key !== $availableGroup['key']) {
                    continue;
                }

                $processed = $this->processRegardlessType($group, $availableGroup);

                if (count($processed) > 0) {
                    $groupsProcessed[$key] = $processed;
                }

                break;
            }
        }

        return $groupsProcessed;
    }

    private function processValues(array $values, array $schema): array
    {
        $valuesProcessed = [];

        foreach (array_unique($values) as $value) {
            if (!is_string($value)) {
                continue;
            }

            foreach ($schema as $availableValue) {
                if ($value === $availableValue['value']) {
                    $valuesProcessed[] = $value;

                    break;
                }
            }
        }

        return $valuesProcessed;
    }

    private function processInputs(array $inputs): array
    {
        $inputsProcessed = [];

        foreach (array_unique($inputs) as $input) {
            if (is_string($input)) {
                $inputsProcessed[] = $input;
            }
        }

        return $inputsProcessed;
    }

    private function processRegardlessType(array $group, array $availableGroup): array
    {
        $type = $availableGroup['value_type'];

        if ('group' === $type) {
            return $this->processGroups($group, $availableGroup['children']);
        }

        if ('string' === $type) {
            return $availableGroup['allow_input']
                ? $this->processInputs($group)
                : $this->processValues($group, $availableGroup['values']);
        }

        return [];
    }
}
