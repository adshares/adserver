<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Common\Application\Factory;

use Adshares\Common\Application\Dto\TaxonomyVersion0\Item;
use Adshares\Common\Application\Dto\TaxonomyVersion0\ItemValue;
use Adshares\Common\Application\Dto\TaxonomyVersion0\Type;

final class TaxonomyItemFactory
{
    public static function fromArray(array $item): Item
    {
        if (!isset($item['values']) && isset($item['data'])) {
            $item['values'] = $item['data'];
            unset($item['data']);
        }

        $values = $item['values'] ?? [];

        return new Item(
            Type::map($item['type']),
            $item['key'],
            $item['label'],
            ...self::fromValues($values)
        );
    }

    private static function fromValues(array $value): array
    {
        return array_map(function (array $listItem) {
            return self::fromValue($listItem);
        }, $value);
    }

    private static function fromValue(array $value): ItemValue
    {
        if (!isset($value['value']) && isset($value['key'])) {
            $value['value'] = $value['key'];
            unset($value['key']);
        }

        return new ItemValue($value['value'], $value['label']);
    }

}
