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

namespace Adshares\Common\Application\Dto\TaxonomyV2;

use Adshares\Common\Application\Factory\TaxonomyV2\TargetingItemFactory;
use Adshares\Common\Domain\Adapter\ArrayableItemCollection;
use Adshares\Common\Exception\InvalidArgumentException;
use Illuminate\Contracts\Support\Arrayable;

class Targeting implements Arrayable
{
    private ArrayableItemCollection $user;
    private ArrayableItemCollection $site;
    private ArrayableItemCollection $device;

    public function __construct(
        ArrayableItemCollection $user,
        ArrayableItemCollection $site,
        ArrayableItemCollection $device
    ) {
        $this->user = $user;
        $this->site = $site;
        $this->device = $device;
    }

    public static function fromArray(array $data): Targeting
    {
        $fields = [
            'user',
            'site',
            'device',
        ];

        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException(sprintf('The field `%s` is required.', $field));
            }
            if (!is_array($data[$field])) {
                throw new InvalidArgumentException(sprintf('The field `%s` must be an array.', $field));
            }
            $targetingItems = new ArrayableItemCollection();
            foreach ($data[$field] as $itemData) {
                $targetingItems->add(TargetingItemFactory::fromArray($itemData));
            }
            $items[$field] = $targetingItems;
        }

        return new self(
            $items['user'],
            $items['site'],
            $items['device']
        );
    }

    public function toArray(): array
    {
        return [
            'user' => $this->user->toArray(),
            'site' => $this->site->toArray(),
            'device' => $this->device->toArray(),
        ];
    }
}
