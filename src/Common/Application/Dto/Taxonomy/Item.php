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

namespace Adshares\Common\Application\Dto\Taxonomy;

use Adshares\Common\Application\Dto\Taxonomy\Item\Type;
use Adshares\Common\Application\Dto\Taxonomy\Item\Value;
use Adshares\Common\Application\Model\Selector;
use Adshares\Common\Application\Model\Selector\Option;
use Adshares\Common\Application\Model\Selector\OptionValue;
use InvalidArgumentException;

use function array_map;

final class Item
{
    /** @var Type */
    private $type;

    /** @var string */
    private $key;

    /** @var string */
    private $label;

    /** @var Value[] */
    private $values;

    /** @var Item[] */
    private $children = [];

    public function __construct(Type $type, string $key, string $label, Value ...$values)
    {
        $this->type = $type;
        $this->key = $key;
        $this->label = $label;

        $this->validateList(...$values);

        $this->values = $values;
    }

    private function validateList(Value ...$values): void
    {
        if (empty($values) && $this->ofType(Type::TYPE_DICTIONARY)) {
            throw new InvalidArgumentException('Dictionary type needs predefined values. None given.');
        }
    }

    public function ofType(string $type): bool
    {
        return $this->type->is($type);
    }

    public function toSelectorOption(): Option
    {
        if ($this->ofType(Type::TYPE_DICTIONARY)) {
            return $this->fromDictionary();
        }

        if ($this->ofType(Type::TYPE_BOOLEAN)) {
            return $this->fromBoolean();
        }

        if ($this->ofType(Type::TYPE_NUMBER)) {
            return $this->fromNumber();
        }

        if ($this->ofType(Type::TYPE_INPUT)) {
            return $this->fromInput();
        }

        return $this->fromGroup();
    }

    private function fromDictionary(): Option
    {
        $values = array_map(function (Value $listItemValue) {
            return $listItemValue->toOptionValue();
        },
            $this->values);

        return (new Option(
            Option::TYPE_STRING,
            $this->key,
            $this->label,
            false
        ))->withValues(...$values);
    }

    private function fromBoolean(): Option
    {
        $values = array_map(function (Value $listItemValue) {
            return $listItemValue->toOptionValue();
        },
            $this->values);

        if (empty($values)) {
            $values = [
                new OptionValue('Yes', 'true'),
                new OptionValue('No', 'false'),
            ];
        }

        return (new Option(
            Option::TYPE_BOOLEAN,
            $this->key,
            $this->label,
            false
        ))->withValues(...$values);
    }

    private function fromNumber(): Option
    {
        return new Option(
            Type::TYPE_NUMBER,
            $this->key,
            $this->label,
            false
        );
    }

    private function fromInput(): Option
    {
        return new Option(
            Option::TYPE_STRING,
            $this->key,
            $this->label,
            true
        );
    }

    private function fromGroup(): Option
    {
        $options = array_map(
            function (Item $item) {
                return $item->toSelectorOption();
            },
            $this->children
        );

        return (new Option(
            Option::TYPE_GROUP,
            $this->key,
            $this->label,
            false
        ))->withSubSelector(new Selector(...$options));
    }

    public function withChildren(self ...$children): self
    {
        $this->children = $children;

        return $this;
    }
}
