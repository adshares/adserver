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
use Adshares\Adserver\Models\Zone;
use Illuminate\Support\Collection;
use stdClass;
use Symfony\Component\HttpFoundation\HeaderUtils;
use function array_shift;

final class ImpressionContext
{
    /** @var array */
    private $site;

    /** @var array */
    private $device;

    /** @var array */
    private $user;

    /** @var array|null */
    private $originalUser;

    public function __construct(array $site, array $device, array $user)
    {
        $this->site = $site;
        $this->device = $device;
        $this->user = $user;
    }

    public function withUserDataReplacedBy(array $userData): self
    {
        $new = clone $this;

        if (empty($new->originalUser)) {
            $new->originalUser = $this->user;
        }

        $new->user = $userData;

        return $new;
    }

    public function toArray(): array
    {
        return [
            'site' => $this->site,
            'device' => $this->device,
            'user' => $this->user,
        ];
    }

    public function adUserRequestBody(): array
    {
        return [
            'url' => $this->site['page'] ?? '',
            'tags' => $this->site['keywords'] ?? [],
            'headers' => $this->flatHeaders(),
        ];
    }

    public function adSelectRequestParams(Collection $zones): array
    {
        $params = [];

        foreach ($zones as $requestId => $zone) {
            $params[] = [
                'keywords' => AbstractFilterMapper::generateNestedStructure($this->user['keywords']),
                'banner_size' => "{$zone->width}x{$zone->height}",
                'publisher_id' => Zone::fetchPublisherPublicIdByPublicId($zone->uuid),
                'request_id' => $requestId,
                'user_id' => $this->trackingId(),
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

    public function trackingId(): string
    {
        $trackingId = $this->user['uid'] ?? $this->cookies()['tid'] ?? $this->originalUser['uid'] ?? '';

        if (!$trackingId) {
            throw new ImpressionContextException('Missing UID - this should not happen');
        }

        return $trackingId;
    }

    private function flatHeaders(): array
    {
        $headers = array_map(
            function ($items) {
                return array_shift($items) ?? $items;
            },
            $this->device['headers'] ?? []
        );

        $headers['user-agent'] = ($headers['user-agent'] ?? $headers['User-Agent'] ?? false)
            ?: ($this->device['ua'] ?? '');

        /** @deprecated Remove when AdUser is ready */
        $headers['User-Agent'] = $headers['user-agent'];

        return $headers;
    }

    private function cookies(): array
    {
        return HeaderUtils::combine(HeaderUtils::split($this->flatHeaders()['cookie'] ?? '', ';='));
    }
}
