<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

use Adshares\Common\Application\Dto\TaxonomyV2\Medium;

class TargetingProcessor
{
    private array $targetingSchema;

    public function __construct(Medium $medium)
    {
        $this->targetingSchema = $medium->getTargeting()->toArray();
    }

    public function processTargeting(array $targeting): array
    {
        if (!$targeting) {
            return [];
        }
        return $this->processGroups($targeting, $this->targetingSchema);
    }

    public function checkIfPathExist(array $path): bool
    {
        $schema = $this->targetingSchema;
        foreach ($path as $entry) {
            if (!isset($schema[$entry])) {
                return false;
            }

            $schema = $schema[$entry];
            if (self::arrayHasDefaultNumericKeys($schema)) {
                $schema = self::createGroupSchemaByKey($schema);
            }
        }
        return true;
    }

    private function processGroups(array $groups, array $schema): array
    {
        $groupsProcessed = [];

        foreach ($groups as $key => $group) {
            if (!is_array($group) || !isset($schema[$key])) {
                continue;
            }

            $processed = $this->processRegardlessType($group, $schema[$key]);

            if (count($processed) > 0) {
                $groupsProcessed[$key] = $processed;
            }
        }

        return $groupsProcessed;
    }

    private static function createGroupSchemaByKey(array $schema): array
    {
        $schemaByKey = [];

        foreach ($schema as $availableGroup) {
            $schemaByKey[$availableGroup['name']] = $availableGroup;
        }

        return $schemaByKey;
    }

    private function processValues(array $values, array $schema): array
    {
        $valuesProcessed = [];

        $availableValuesByValue = self::extractAvailableValuesByValue($schema);

        foreach (array_unique($values) as $value) {
            if (!is_string($value) || !isset($availableValuesByValue[$value])) {
                continue;
            }

            $valuesProcessed[] = $value;
        }

        return $valuesProcessed;
    }

    private static function extractAvailableValuesByValue(array $schema): array
    {
        $availableValues = [];

        foreach ($schema as $key => $value) {
            $availableValues[$key] = $key;

            if (isset($value['values'])) {
                $availableValues =
                    array_merge($availableValues, self::extractAvailableValuesByValue($value['values']));
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
        if (self::arrayHasDefaultNumericKeys($availableGroup)) {
            return $this->processGroups($group, self::createGroupSchemaByKey($availableGroup));
        }

        $type = $availableGroup['type'];

        if ('dict' === $type) {
            return $this->processValues($group, $availableGroup['items']);
        }

        if ('input' === $type) {
            return $this->processInputs($group);
        }

        return [];
    }

    private static function arrayHasDefaultNumericKeys(array $array): bool
    {
        return array_keys($array) === range(0, count($array) - 1);
    }
}
