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

namespace Adshares\Common\Application\Factory;

use Adshares\Common\Application\Dto\Taxonomy\Item;
use Adshares\Common\Application\Dto\Taxonomy\Item\Type;
use Adshares\Common\Application\Dto\Taxonomy\Item\Value;

final class TaxonomyItemFactory
{
    /** @var string[] */
    public const MAP_TYPE = [
        '' => Type::TYPE_GROUP,
        'num' => Type::TYPE_NUMBER,
        'number' => Type::TYPE_NUMBER,
        'bool' => Type::TYPE_BOOLEAN,
        'boolean' => Type::TYPE_BOOLEAN,
        'dict' => Type::TYPE_DICTIONARY,
        'list' => Type::TYPE_DICTIONARY,
        'input' => Type::TYPE_INPUT,
        'text' => Type::TYPE_INPUT,
        'string' => Type::TYPE_INPUT,
    ];

    public static function fromArray(array $item): Item
    {
        if (!isset($item['values']) && isset($item['data'])) {
            $item['values'] = $item['data'];
            unset($item['data']);
        }

        if (!isset($item['values']) && isset($item['list'])) {
            $item['values'] = $item['list'];
            unset($item['list']);
        }

        if (!isset($item['type'])) {
            $item['type'] = 'dict';
        }

        return new Item(
            self::map($item['type']),
            $item['key'],
            $item['label'],
            ...self::mapValues($item['values'] ?? [])
        );
    }

    public static function groupingItem(string $key, string $label, Item ...$items): Item
    {
        return (new Item(
            new Type(Type::TYPE_GROUP),
            $key,
            $label
        ))->withChildren(...$items);
    }

    public static function map($value): Type
    {
        return new Type(self::MAP_TYPE[$value]);
    }

    private static function mapValues(array $values): array
    {
        return array_map(
            function (array $listItem) {
                return self::mapValue($listItem);
            },
            $values
        );
    }

    private static function mapValue(array $value): Value
    {
        if (!isset($value['value']) && isset($value['key'])) {
            $value['value'] = $value['key'];
            unset($value['key']);
        }

        $values = isset($value['values']) ? self::mapValues($value['values']) : [];

        return new Value($value['value'], $value['label'], $values, $value['description'] ?? null);
    }
}
