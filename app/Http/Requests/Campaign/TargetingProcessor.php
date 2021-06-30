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

        $groupSchemaByKey = $this->createGroupSchemaByKey($schema);

        foreach ($groups as $key => $group) {
            if (!is_array($group) || !isset($groupSchemaByKey[$key])) {
                continue;
            }

            $processed = $this->processRegardlessType($group, $groupSchemaByKey[$key]);

            if (count($processed) > 0) {
                $groupsProcessed[$key] = $processed;
            }
        }

        return $groupsProcessed;
    }

    private function createGroupSchemaByKey(array $schema): array
    {
        $schemaByKey = [];

        foreach ($schema as $availableGroup) {
            $schemaByKey[$availableGroup['key']] = $availableGroup;
        }

        return $schemaByKey;
    }

    private function processValues(array $values, array $schema): array
    {
        $valuesProcessed = [];

        $availableValuesByValue = $this->extractAvailableValuesByValue($schema);

        foreach (array_unique($values) as $value) {
            if (!is_string($value) || !isset($availableValuesByValue[$value])) {
                continue;
            }

            $valuesProcessed[] = $value;
        }

        return $valuesProcessed;
    }

    private function extractAvailableValuesByValue(array $schema): array
    {
        $availableValues = [];

        foreach ($schema as $availableValue) {
            $value = $availableValue['value'];
            $availableValues[$value] = $value;

            if (isset($availableValue['values'])) {
                $availableValues =
                    array_merge($availableValues, $this->extractAvailableValuesByValue($availableValue['values']));
            }
        }

        return $availableValues;
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
