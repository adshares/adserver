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

use Adshares\Common\Application\Factory\TaxonomyV4\TargetingItemFactory;
use Adshares\Common\Domain\Adapter\ArrayableItemCollection;
use Adshares\Common\Exception\InvalidArgumentException;
use Illuminate\Contracts\Support\Arrayable;

class Medium implements Arrayable
{
    private string $name;
    private string $label;
    private ArrayableItemCollection $formats;
    private Targeting $targeting;

    public function __construct(string $name, string $label, ArrayableItemCollection $formats, Targeting $targeting)
    {
        $this->name = $name;
        $this->label = $label;
        $this->formats = $formats;
        $this->targeting = $targeting;
    }

    public static function fromArray(array $data): self
    {
        self::validate($data);

        $formats = new ArrayableItemCollection();
        foreach ($data['formats'] as $formatData) {
            $formats->add(Format::fromArray($formatData));
        }
        $targeting = Targeting::fromArray($data['targeting']);

        return new self(
            $data['name'],
            $data['label'],
            $formats,
            $targeting,
        );
    }

    private static function validate(array $data): void
    {
        $fields = [
            'name',
            'label',
            'formats',
            'targeting',
        ];

        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException(sprintf('The field `%s` is required.', $field));
            }
        }
        if (!is_string($data['name'])) {
            throw new InvalidArgumentException('The field `name` must be a string.');
        }
        if (!is_string($data['label'])) {
            throw new InvalidArgumentException('The field `label` must be a string.');
        }
        if (!is_array($data['formats'])) {
            throw new InvalidArgumentException('The field `formats` must be an array.');
        }
        if (empty($data['formats'])) {
            throw new InvalidArgumentException('The field `formats` must be a non-empty array.');
        }
        if (!is_array($data['targeting'])) {
            throw new InvalidArgumentException('The field `targeting` must be an array.');
        }
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'formats' => $this->formats->toArray(),
            'targeting' => $this->targeting->toArray(),
        ];
    }
}
