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
        $meta = Meta::fromArray($data['meta']);
        $media = new ArrayableItemCollection();
        foreach ($data['media'] as $mediumData) {
            $media->add(Medium::fromArray($mediumData));
        }

        return new self($meta, $media);
    }

    public function toArray(): array
    {
        return [
            'meta' => $this->meta->toArray(),
            'media' => $this->media->toArray(),
        ];
    }
}
