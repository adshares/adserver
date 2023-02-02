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

declare(strict_types=1);

namespace Adshares\Adserver\Http\Response\Site;

use Adshares\Adserver\Models\Site;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

class SizesResponse implements Arrayable
{
    private Collection $sizes;

    public function __construct(?int $siteId = null)
    {
        $this->sizes = $this->getZones($siteId)
            ->map(fn ($zone) => $zone->scopes)
            ->flatten()
            ->unique()
            ->values();
    }

    public function toArray(): array
    {
        return ['sizes' => $this->sizes->toArray()];
    }

    private function getZones(?int $siteId): Collection
    {
        if (null === $siteId) {
            $sites = Site::get();
            if (!$sites) {
                return new Collection();
            }
            $zones = $sites->map(fn ($site) => $site->zones)
                ->flatten();
        } else {
            $site = Site::fetchById($siteId);
            if (!$site) {
                return new Collection();
            }
            $zones = $site->zones;
        }

        return $zones;
    }
}
