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

final class Schema
{
    private const SCHEMA_PREFIX = 'urn:x-adshares:taxonomy';

    /** @var string */
    private $value;

    private function __construct(string $value)
    {
        $this->validateSchema($value);
        $this->value = $value;
    }

    private function validateSchema(string $schema): void
    {
        if (stripos($schema, self::SCHEMA_PREFIX) !== 0) {
            throw new InvalidArgumentException("Schema '$schema' does not match: ".self::SCHEMA_PREFIX);
        }
    }

    public static function fromString(string $string): self
    {
        return new self($string);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
