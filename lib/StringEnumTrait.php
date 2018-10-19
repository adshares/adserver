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

namespace Lib;

use RuntimeException;
use UnexpectedValueException;

trait StringEnumTrait
{
    /** @var string */
    private $value;

    public function __construct(string $value)
    {
        $this->failIfInvalidValueArray();
        $this->failIfTypeNotAllowed($value);

        $this->value = $value;
    }

    private function failIfInvalidValueArray(): void
    {
        if (empty(self::ALLOWED_VALUES)) {
            throw new RuntimeException("ALLOWED_VALUES have to be defined as a non-empty array of strings");
        }
    }

    private function failIfTypeNotAllowed(string $value): void
    {
        if (!empty(self::ALLOWED_VALUES) && !\in_array($value, self::ALLOWED_VALUES, true)) {
            $values = implode(', ', self::ALLOWED_VALUES);
            throw new UnexpectedValueException("Value '$value' must be one of ($values)");
        }
    }
}
