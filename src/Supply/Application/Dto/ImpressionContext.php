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

use Adshares\Adserver\Models\Zone;
use Illuminate\Support\Collection;
use function array_filter;

final class ImpressionContext
{
    private const ACCIO = 'accio:';

    /** @var array */
    private $site;

    /** @var array */
    private $device;

    /** @var array */
    private $user;

    public function __construct(array $site, array $device, array $user)
    {
        $userKeywords = array_filter(
            $site['keywords'],
            function (string $keyword) {
                return stripos($keyword, self::ACCIO) === 0;
            }
        );

        foreach ($userKeywords as $keyword) {
            $user['keywords']['interest'] = str_replace('accio:', '', $keyword);
        }

        $site['keywords'] = array_filter(
            $site['keywords'],
            function (string $keyword) {
                return stripos($keyword, self::ACCIO) !== 0;
            }
        );

        $this->site = $site;
        $this->device = $device;
        $this->user = $user;
    }

    public function adUserRequestBody(): string
    {
        return <<<"JSON"
{
    "domain": "{$this->site['domain']}",
    "ip": "{$this->device['ip']}",
    "ua": "{$this->device['ua']}",
    "uid": "{$this->user['uid']}"
}
JSON;
    }

    public function adSelectRequestParams(Collection $zones): array
    {
        return $zones->map(
            function (Zone $zone) {
                return [
                    'keywords' => $this->user['keywords'],
                    'banner_size' => "{$zone->width}x{$zone->height}",
                    'publisher_id' => 'pid',
                    'request_id' => $zone->id,
                    'user_id' => $this->user['uid'],
                ];
            }
        )->toArray();
    }

    public function keywords()
    {
        return $this->site['keywords'];
    }
}
