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

namespace Adshares\Common\Domain\ValueObject;

use InvalidArgumentException;
use function array_filter;
use function in_array;

final class TargetingOption
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
    /** @var TargetingOptions */
    private $children;
    /** @var TargetingOptionValue[] */
    private $values;

    public function __construct(
        ?string $type,
        string $key,
        string $label,
        ?bool $allowInput,
        TargetingOptions $children,
        TargetingOptionValue ...$values
    ) {
        if (($type ?? false) && !in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException('Type has to be one of ['.implode(',', self::TYPES)."]. Is: $type");
        }
        $this->type = $type;
        $this->key = $key;
        $this->label = $label;
        $this->allowInput = $allowInput;
        $this->children = $children;
        $this->values = $values;
    }

    public static function fromArray(array $item): self
    {
        $values = array_map(function (array $value) {
            return TargetingOptionValue::fromArray($value);
        }, $item['values'] ?? []);

        return new self(
            $item['value_type'] ?? null,
            $item['key'],
            $item['label'],
            $item['allow_input'] ?? null,
            TargetingOptions::fromArray($item['children'] ?? []),
            ...$values
        );
    }

    public function toArrayRecursive(): array
    {
        return array_filter([
            'value_type' => $this->type,
            'key' => $this->key,
            'label' => $this->label,
            'allow_input' => $this->allowInput,
            'children' => $this->children->toArrayRecursive(),
            'values' => array_map(function (TargetingOptionValue $option) {
                return $option->toArray();
            }, $this->values),
        ], function ($item) {
            return null !== $item;
        });
    }
}
