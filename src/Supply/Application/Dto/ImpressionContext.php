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

namespace Adshares\Supply\Application\Dto;

use Adshares\Adserver\Client\Mapper\AbstractFilterMapper;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Zone;
use Illuminate\Support\Collection;
use stdClass;

final class ImpressionContext
{
    /** @var array */
    private $site;

    /** @var array */
    private $device;

    /** @var array */
    private $user;

    public function __construct(array $site, array $device, array $user)
    {
        $this->site = $site;
        $this->device = $device;
        $this->user = $user;
    }

    public function withUserDataReplacedBy(array $userData): self
    {
        $new = clone $this;

        $new->user = $userData;

        return $new;
    }

    public function adUserRequestBody(): array
    {
        return [
            'site' => $this->site,
            'device' => $this->device,
            //BC with AdUser
            'headers' => $this->device['headers'] ?? [],
        ];
    }

    public function adSelectRequestParams(Collection $zones): array
    {
        $params = [];

        foreach ($zones as $requestId => $zone) {
            $params[] = [
                'keywords' => AbstractFilterMapper::generateNestedStructure($this->user['keywords'] ?? []),
                'banner_size' => "{$zone->width}x{$zone->height}",
                'publisher_id' => Zone::fetchPublisherPublicIdByPublicId($zone->uuid),
                'request_id' => $requestId,
                'user_id' => $this->user['uid'] ?? '',
                'banner_filters' => $this->getBannerFilters($zone),
            ];
        }

        return $params;
    }

    private function getBannerFilters($zone): array
    {
        /** @var array $filtering */
        $filtering = $zone->site->filtering;

        $bannerFilters = [];
        $bannerFilters['require'] = $filtering['requires'] ?: new stdClass();
        $bannerFilters['exclude'] = $filtering['excludes'] ?: new stdClass();

        return $bannerFilters;
    }

    public function keywords(): array
    {
        return $this->site['keywords'] ?? [];
    }

    public function userId(): string
    {
        return ($this->user['uid'] ?? '') ?: Utils::createTrackingId((string)config('app.adserver_secret'));
    }

    public function eventContext(): array
    {
        return [
            'site' => $this->site,
            'device' => $this->device,
            'user' => $this->user,
        ];
    }
}
