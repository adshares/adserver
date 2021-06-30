<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Http\Response\Classifier;

use Adshares\Adserver\Models\Classification;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

class ClassifierResponse implements Arrayable
{
    /** @var Item[] */
    private $items = [];

    public function __construct(Collection $banners, Collection $classifications, ?int $siteId = null)
    {
        foreach ($banners as $banner) {
            $globalStatus = $this->globalStatusForBanner($banner->id, $classifications);
            $siteStatus = $this->siteStatusForBanner($banner->id, $classifications, $siteId);
            $this->items[] = new Item(
                (int)$banner->id,
                $banner->serve_url,
                $banner->type,
                $banner->size,
                $banner->landing_url,
                $banner->source_host,
                (int)$banner->budget,
                (int)$banner->max_cpm,
                (int)$banner->max_cpc,
                $globalStatus,
                $siteStatus
            );
        }
    }

    private function globalStatusForBanner($bannerId, Collection $classifications): ?bool
    {
        $item = $classifications->filter(
            function (Classification $classification) use ($bannerId) {
                return (int)$classification->banner_id === $bannerId && $classification->site_id === null;
            }
        )->first();

        return isset($item->status) ? (bool)$item->status : null;
    }

    private function siteStatusForBanner($bannerId, Collection $classifications, ?int $siteId): ?bool
    {
        if (!$siteId) {
            return null;
        }

        $item = $classifications->filter(
            function (Classification $classification) use ($bannerId, $siteId) {
                return (int)$classification->banner_id === $bannerId && (int)$classification->site_id === $siteId;
            }
        )->first();

        return isset($item->status) ? (bool)$item->status : null;
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_map(
            function (Item $item) {
                return $item->toArray();
            },
            $this->items
        );
    }
}
