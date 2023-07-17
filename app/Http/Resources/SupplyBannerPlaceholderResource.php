<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Http\Resources;

use Adshares\Adserver\Models\SupplyBannerPlaceholder;
use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;
use Ramsey\Uuid\Uuid;

class SupplyBannerPlaceholderResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var SupplyBannerPlaceholder $this */
        return [
            'id' => Uuid::fromString($this->uuid)->toString(),
            'createdAt' => $this->created_at->format(DateTimeInterface::ATOM),
            'updatedAt' => $this->updated_at->format(DateTimeInterface::ATOM),
            'medium' => $this->medium,
            'scope' => $this->size,
            'type' => $this->type,
            'mime' => $this->mime,
            'isDefault' => $this->is_default,
            'checksum' => $this->checksum,
            'url' => $this->serve_url,
        ];
    }
}
