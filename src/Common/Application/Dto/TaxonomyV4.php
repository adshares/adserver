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

namespace Adshares\Common\Application\Dto;

use Adshares\Common\Application\Dto\TaxonomyV4\Medium;
use Adshares\Common\Application\Dto\TaxonomyV4\Meta;
use Adshares\Common\Domain\Adapter\ArrayableItemCollection;
use Adshares\Common\Exception\InvalidArgumentException;
use Illuminate\Contracts\Support\Arrayable;

class TaxonomyV4 implements Arrayable
{
    private Meta $meta;
    private ArrayableItemCollection $media;

    public function __construct(Meta $meta, ArrayableItemCollection $media)
    {
        $this->meta = $meta;
        $this->media = $media;
    }

    public static function fromArray(array $data): self
    {
        $fields = [
            'meta',
            'media',
        ];

        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                throw new InvalidArgumentException(sprintf('The field `%s` is required.', $field));
            }
            if (!is_array($data[$field])) {
                throw new InvalidArgumentException(sprintf('The field `%s` must be an array.', $field));
            }
        }

        foreach ($data['media'] as $mediaData) {
            if (!is_array($mediaData)) {
                throw new InvalidArgumentException('The field `media[]` must be an array.');
            }
        }

        $meta = Meta::fromArray($data['meta']);
        $media = new ArrayableItemCollection();
        foreach ($data['media'] as $mediumData) {
            $media->add(Medium::fromArray($mediumData));
        }

        return new self($meta, $media);
    }

    /**
     * @return Medium[]|ArrayableItemCollection
     */
    public function getMedia(): ArrayableItemCollection
    {
        return $this->media;
    }

    public function toArray(): array
    {
        return [
            'meta' => $this->meta->toArray(),
            'media' => $this->media->toArray(),
        ];
    }
}
