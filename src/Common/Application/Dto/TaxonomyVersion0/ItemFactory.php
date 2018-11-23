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

namespace Adshares\Common\Application\Dto\TaxonomyVersion0;

final class ItemFactory
{
    public static function fromArray(array $item): Item
    {
        if (!isset($item['list'])) {
            if (isset($item['data'])) {
                $item['list'] = $item['data'];
                unset($item['data']);
            } elseif (isset($item['values'])) {
                $item['list'] = $item['values'];
                unset($item['values']);
            }
        }

        $list = $item['list'] ?? [];

        return new Item(
            Type::map($item['type']),
            $item['key'],
            $item['label'],
            ...self::fromList($list)
        );
    }

    private static function fromList(array $list): array
    {
        return array_map(function (array $listItem) {
            if (!isset($listItem['value']) && isset($listItem['key'])) {
                $listItem['value'] = $listItem['key'];
                unset($listItem['key']);
            }

            return new ListItemValue($listItem['value'], $listItem['label']);
        }, $list);
    }
}
