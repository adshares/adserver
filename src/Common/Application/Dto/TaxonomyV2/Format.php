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

namespace Adshares\Common\Application\Dto\TaxonomyV2;

use Adshares\Common\Exception\InvalidArgumentException;
use Illuminate\Contracts\Support\Arrayable;

class Format implements Arrayable
{
    private string $type;
    /**
     * @var string[]
     */
    private array $mimes;
    /**
     * @var array<string, string>
     */
    private array $scopes;

    public function __construct(string $type, array $mimes, array $scopes)
    {
        $this->type = $type;
        $this->mimes = $mimes;
        $this->scopes = $scopes;
    }

    public static function fromArray(array $data): self
    {
        self::validate($data);

        return new self(
            $data['type'],
            $data['mimes'],
            $data['scopes'],
        );
    }

    private static function validate(array $data): void
    {
        $fields = [
            'type',
            'mimes',
            'scopes',
        ];

        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException(sprintf('The field `%s` is required.', $field));
            }
        }

        self::validateType($data['type']);
        self::validateMimes($data);
        self::validateScopes($data);
    }

    private static function validateType($type): void
    {
        if (!is_string($type)) {
            throw new InvalidArgumentException('The field `type` must be a string.');
        }
    }

    private static function validateMimes(array $data): void
    {
        if (!is_array($data['mimes'])) {
            throw new InvalidArgumentException('The field `mimes` must be an array.');
        }

        if (empty($data['mimes'])) {
            throw new InvalidArgumentException('The field `mimes` must be a non-empty array.');
        }

        foreach ($data['mimes'] as $mime) {
            if (!is_string($mime)) {
                throw new InvalidArgumentException('The field `mimes` must be a string array.');
            }
        }
    }

    private static function validateScopes(array $data): void
    {
        if (!is_array($data['scopes'])) {
            throw new InvalidArgumentException('The field `scopes` must be an array.');
        }

        if (empty($data['scopes'])) {
            throw new InvalidArgumentException('The field `scopes` must be a non-empty array.');
        }

        foreach ($data['scopes'] as $scopeKey => $scopeLabel) {
            if (!is_string($scopeKey)) {
                throw new InvalidArgumentException('Each key in the `scopes` field must be a string.');
            }
            if (!is_string($scopeLabel)) {
                throw new InvalidArgumentException('Each label in the `scopes` field must be a string.');
            }
        }
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return string[]
     */
    public function getMimes(): array
    {
        return $this->mimes;
    }

    /**
     * @return array<string, string>
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'mimes' => $this->mimes,
            'scopes' => $this->scopes,
        ];
    }
}
