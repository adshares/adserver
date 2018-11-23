<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your Selector\Option) any later version.
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

use Adshares\Common\Application\Dto\Selector;
use Adshares\Common\Application\Dto\Selector\OptionValue;
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
    /** @var ListItemValue[] */
    private $list;

    public function __construct(Type $type, string $key, string $label, ListItemValue ...$list)
    {
        $this->type = $type;
        $this->key = $key;
        $this->label = $label;

        $this->validateList(...$list);

        $this->list = $list;
    }

    private function validateList(ListItemValue ...$list): void
    {
        if (empty($list) && $this->type->is(Type::TYPE_DICTIONARY)) {
            throw new InvalidArgumentException('Dictionary type needs predefined values. None given.');
        }
    }

    public function toSelectorOption(): Selector\Option
    {
        if ($this->type->is(Type::TYPE_DICTIONARY)) {
            return $this->fromDictionary();
        }

        if ($this->type->is(Type::TYPE_BOOLEAN)) {
            return $this->fromBoolean();
        }

        if ($this->type->is(Type::TYPE_INPUT)) {
            return $this->fromInput();
        }

        if ($this->type->is(Type::TYPE_NUMBER)) {
            return $this->fromNumber();
        }
    }

    private function fromDictionary(): Selector\Option
    {
        $values = array_map(function (ListItemValue $listItemValue) {
            return $listItemValue->toOptionValue();
        }, $this->list);

        return new Selector\Option(
            Selector\Option::TYPE_STRING,
            $this->key,
            $this->label,
            false,
            new Selector(),
            ...$values
        );
    }

    private function fromBoolean(): Selector\Option
    {
        $defaultBooleanValues = [
            new OptionValue('Yes', 'true'),
            new OptionValue('No', 'false'),
        ];

        return new Selector\Option(
            Selector\Option::TYPE_BOOLEAN,
            $this->key,
            $this->label,
            false,
            new Selector(),
            ...$defaultBooleanValues
        );
    }

    private function fromInput(): Selector\Option
    {
        return new Selector\Option(
            Selector\Option::TYPE_STRING,
            $this->key,
            $this->label,
            true,
            new Selector()
        );

    }

    private function fromNumber(): Selector\Option
    {
        return new Selector\Option(Type::TYPE_NUMBER,
            $this->key, $this->label, false, new Selector());
    }

}
