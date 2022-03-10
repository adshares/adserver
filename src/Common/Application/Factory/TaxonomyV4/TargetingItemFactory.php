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

namespace Adshares\Common\Application\Factory\TaxonomyV4;

use Adshares\Common\Application\Dto\TaxonomyV4\DictionaryTargetingItem;
use Adshares\Common\Application\Dto\TaxonomyV4\InputTargetingItem;
use Adshares\Common\Application\Dto\TaxonomyV4\TargetingItem;
use Adshares\Common\Exception\InvalidArgumentException;

class TargetingItemFactory
{
    public static function fromArray(array $data): TargetingItem
    {
        self::validateCommonFields($data);

        if ('input' === $data['type']) {
            return new InputTargetingItem(
                $data['name'],
                $data['label']
            );
        }

        if ('dict' === $data['type']) {
            self::validateDictionary($data);
            return new DictionaryTargetingItem(
                $data['name'],
                $data['label'],
                $data['items']
            );
        }

        throw new InvalidArgumentException('Invalid `type`.');
    }

    private static function validateCommonFields(array $data): void
    {
        $fields = [
            'type',
            'name',
            'label',
        ];

        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException(sprintf('The field `%s` is required.', $field));
            }
            if (!is_string($data[$field])) {
                throw new InvalidArgumentException(sprintf('The field `%s` must be a string.', $field));
            }
        }
    }

    private static function validateDictionary(array $data): void
    {
        if (!array_key_exists('items', $data)) {
            throw new InvalidArgumentException('The field `items` is required.');
        }
        if (!is_array($data['items'])) {
            throw new InvalidArgumentException('The field `items` must be an array.');
        }
        if (empty($data['items'])) {
            throw new InvalidArgumentException('The field `items` must be a non-empty array.');
        }
        foreach ($data['items'] as $itemKey => $itemValue) {
            self::validateItem($itemKey, $itemValue);
        }
    }

    private static function validateItem($itemKey, $itemValue): void
    {
        if (!is_string($itemKey)) {
            throw new InvalidArgumentException('Each key must be a string.');
        }
        if (is_array($itemValue)) {
            self::validateItemLabel($itemValue);
            self::validateItemValues($itemValue);
        } else {
            if (!is_string($itemValue)) {
                throw new InvalidArgumentException('Each value in the `items` field must be a string.');
            }
        }
    }

    private static function validateItemLabel(array $itemValue): void
    {
        if (!array_key_exists('label', $itemValue)) {
            throw new InvalidArgumentException('The field `label` is required in nested item.');
        }
        if (!is_string($itemValue['label'])) {
            throw new InvalidArgumentException('The field `label` must be a string.');
        }
    }

    private static function validateItemValues(array $itemValue): void
    {
        if (!array_key_exists('values', $itemValue)) {
            throw new InvalidArgumentException('The field `values` is required in nested item.');
        }
        if (!is_array($itemValue['values'])) {
            throw new InvalidArgumentException('The field `value` must be an array.');
        }
        if (empty($itemValue['values'])) {
            throw new InvalidArgumentException('The field `value` must be a non-empty array.');
        }
        foreach ($itemValue['values'] as $key => $value) {
            self::validateItem($key, $value);
        }
    }
}
