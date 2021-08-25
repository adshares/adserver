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

namespace Adshares\Common\Application\Model;

use Adshares\Common\Application\Dto\Taxonomy;
use Adshares\Common\Application\Dto\Taxonomy\Item;
use Adshares\Common\Application\Model\Selector\Option;

use function array_filter;

final class Selector
{
    /** @var Option[] */
    private array $options;

    private array $exclusions = [];

    public function __construct(Option ...$options)
    {
        $this->options = $options;
    }

    public function exclude(array $exclusions): self
    {
        $this->exclusions = $exclusions;
        foreach ($this->options as $option) {
            $option->exclude($exclusions);
        }
        return $this;
    }

    public static function fromTaxonomy(Taxonomy $taxonomy): Selector
    {
        return new self(...array_map(
            static function (Item $item) {
                return $item->toSelectorOption();
            },
            $taxonomy->toArray()
        ));
    }

    public function toArrayRecursiveWithoutEmptyFields(string $path = ''): array
    {
        return array_values(
            array_filter(
                array_map(
                    function (Option $option) use ($path) {
                        $subPath = $path . '/' . $option->key();
                        $exclusion = $this->exclusions[$subPath] ?? false;
                        if ($exclusion === true) {
                            return [];
                        }
                        return $option->toArrayRecursiveWithoutEmptyFields(
                            $subPath,
                            is_array($exclusion) ? $exclusion : []
                        );
                    },
                    $this->onlyViewable()
                )
            )
        );
    }

    public function isEmpty(): bool
    {
        return empty($this->onlyViewable());
    }

    private function onlyViewable(): array
    {
        return array_values(array_filter(
            $this->options,
            static function (Option $option) {
                return $option->isViewable();
            }
        ));
    }
}
