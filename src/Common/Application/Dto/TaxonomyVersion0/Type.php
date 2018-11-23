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

use InvalidArgumentException;
use function in_array;

final class Type
{
    public const TYPE_NUMBER = 'number';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_DICTIONARY = 'dictionary';
    public const TYPE_INPUT = 'input';
    public const TYPE_LIST = 'list';
    private const TYPES = [
        self::TYPE_NUMBER,
        self::TYPE_BOOLEAN,
        self::TYPE_DICTIONARY,
        self::TYPE_INPUT,
        self::TYPE_LIST,
    ];

    /** @var string[] */
    private const MAP_TYPE = [
        'num' => self::TYPE_NUMBER,
        'bool' => self::TYPE_BOOLEAN,
        'dict' => self::TYPE_DICTIONARY,
        'input' => self::TYPE_INPUT,
        'list' => self::TYPE_LIST,
    ];

    /** @var string */
    private $value;

    private function __construct(string $value)
    {
        if (!in_array($value, self::TYPES, true)) {
            throw new InvalidArgumentException('Type has to be one of ['.implode(',', self::TYPES)."]. Is: $value");
        }

        $this->value = $value;
    }

    public static function fromAdUser($value): self
    {
        return new self(self::MAP_TYPE[$value]);
    }

    public function is(string $type): bool
    {
        return $type === $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
