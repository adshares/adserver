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

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Zone;
use Illuminate\Support\Collection;
use function array_filter;
use function GuzzleHttp\json_encode;

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
        if (config('app.env') === Utils::ENV_DEV) {
            [$user, $site] = $this->accioFilter($user, $site);
        }

        $this->site = $site;
        $this->device = $device;
        $this->user = $user;
    }

    /** @deprecated
     * @param array $user
     * @param array $site
     *
     * @return array
     */
    private function accioFilter(array $user, array $site): array
    {
        if (!isset($site['keywords'])) {
            return [$user, $site];
        }

        $userKeywords = array_filter(
            $site['keywords'],
            function (string $keyword) {
                return stripos($keyword, self::ACCIO) === 0;
            }
        );

        if (!isset($user['keywords']['interest'])) {
            $user['keywords']['interest'] = [];
        }

        foreach ($userKeywords as $keyword) {
            $user['keywords']['interest'][] = str_replace(self::ACCIO, '', $keyword);
        }

        $site['keywords'] = array_filter(
            $site['keywords'],
            function (string $keyword) {
                return stripos($keyword, self::ACCIO) !== 0;
            }
        );

        return [$user, $site];
    }

    public function adUserRequestBody(): string
    {
        $uid = config('app.adserver_id').'_'.$this->user['uid'];

        return $this->toJson($uid);
    }

    private function toJson(string $uid): string
    {
        return json_encode($this->toArray($uid));
    }

    private function toArray(string $uid): array
    {
        return [
            'domain' => $this->site['domain'],
            'ip' => $this->device['ip'],
            'ua' => $this->device['ua'],
            'uid' => $uid,
        ];
    }

    public function adSelectRequestParams(Collection $zones): array
    {
        return $zones->map(
            function (Zone $zone) {
                return [
                    'keywords' => $this->user['keywords'],
                    'banner_size' => "{$zone->width}x{$zone->height}",
                    'publisher_id' => Zone::fetchPublisherId($zone->id),
                    'request_id' => $zone->id,
                    'user_id' => $this->user['uid'],
                ];
            }
        )->toArray();
    }

    public function keywords(): array
    {
        return $this->site['keywords'] ?? [];
    }

    public function userId(): string
    {
        return $this->user['uid'] ?? '';
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
