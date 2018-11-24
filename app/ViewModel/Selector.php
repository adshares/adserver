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

namespace Adshares\Adserver\ViewModel;

use Adshares\Common\Application\Dto\Taxonomy;
use Adshares\Common\Application\Dto\Taxonomy\Item;
use Adshares\Common\Domain\Adapter\ArrayCollection;
use Illuminate\Contracts\Support\Arrayable;

final class Selector extends ArrayCollection implements Arrayable
{
    public function __construct(Selector\Option ...$items)
    {
        parent::__construct($items);
    }

    public function toArray(): array
    {
        return array_map(function (Selector\Option $option) {
            return $option->toArrayRecursiveWithoutEmptyFields();
        }, parent::toArray());
    }

    public static function fromTaxonomy(Taxonomy $taxonomy): Selector
    {
        return new Selector(...array_map(function (Item $item) {
            return $item->toSelectorOption();
        }, $taxonomy->toArray()));
    }
}
