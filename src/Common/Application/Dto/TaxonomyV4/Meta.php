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

namespace Adshares\Common\Application\Dto\TaxonomyV4;

use Adshares\Common\Domain\ValueObject\SemVer;
use Adshares\Common\Exception\InvalidArgumentException;
use Illuminate\Contracts\Support\Arrayable;

class Meta implements Arrayable
{
    private string $name;
    private SemVer $version;

    public function __construct($name, $version)
    {
        $this->name = $name;
        $this->version = $version;
    }

    public static function fromArray(array $data): self
    {
        $fields = [
            'name',
            'version',
        ];

        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException(sprintf('The field `%s` is required.', $field));
            }
            if (!is_string($data[$field])) {
                throw new InvalidArgumentException(sprintf('The field `%s` must be a string.', $field));
            }
        }

        return new self($data['name'], SemVer::fromString($data['version']));
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => (string)$this->version,
        ];
    }
}
