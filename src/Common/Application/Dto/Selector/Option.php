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

namespace Adshares\Common\Application\Dto\Selector;

use Adshares\Common\Application\Dto\Selector;
use InvalidArgumentException;
use function array_filter;
use function in_array;
use function is_bool;

final class Option
{
    public const TYPE_STRING = 'string';
    public const TYPE_NUMBER = 'number';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPES = [self::TYPE_STRING, self::TYPE_NUMBER, self::TYPE_BOOLEAN];
    /** @var string */
    private $type;
    /** @var string */
    private $key;
    /** @var string */
    private $label;
    /** @var bool */
    private $allowInput;
    /** @var Selector */
    private $children;
    /** @var OptionValue[] */
    private $values = [];

    public function __construct(
        ?string $type,
        string $key,
        string $label,
        bool $allowInput
    ) {
        if (($type ?? false) && !in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Type has to be one of ['.implode(',', self::TYPES)."]. Is: $type");
        }
        $this->type = $type;
        $this->key = $key;
        $this->label = $label;
        $this->allowInput = $allowInput;
        $this->children = new Selector();
    }

    public static function fromArray(array $item): self
    {
        $values = array_map(function (array $value) {
            return OptionValue::fromArray($value);
        }, $item['values'] ?? []);

        return (new self(
            $item['value_type'] ?? null,
            $item['key'],
            $item['label'],
            $item['allow_input'] ?? null
        ))
            ->withChildren(Selector::fromArray($item['children'] ?? []))
            ->withValues(...$values);
    }

    public function withValues(OptionValue ...$values)
    {
        $this->values = $values;

        return $this;
    }

    public function withChildren(Selector $children): self
    {
        $this->children = $children;

        return $this;
    }

    public function toArrayRecursive(): array
    {
        return array_filter([
            'value_type' => $this->type,
            'key' => $this->key,
            'label' => $this->label,
            'allow_input' => $this->allowInput,
            'children' => array_map(function (Option $option) {
                return $option->toArrayRecursive();
            }, $this->children->toArray()),
            'values' => array_map(function (OptionValue $option) {
                return $option->toArray();
            }, $this->values),
        ], function ($item) {
            return !empty($item) || is_bool($item);
        });
    }
}
