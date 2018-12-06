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

use function array_keys;

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

    /** @deprecated This needs to include all data */
    public function adUserRequestBody(): string
    {
        return <<<"JSON"
{
    "domain": "{$this->site['domain']}",
    "ip": "192.168.10.10",
    "ua": "Mozilla/5.0 (X11; U; Linux i686; pl-PL; rv:1.7.10) Gecko/20050717 Firefox/1.0.6",
    "uid": "{$this->user['uid']}"
}
JSON;
    }

    public function adSelectRequestParams(array $zones): array
    {
        return array_map(
            function (array $param) {
                if (isset($param['keywords']) && empty($param['keywords'])) {
                    unset($param['keywords']);
                }

                return $param;
            },
            $this->fixedParams($zones)
        );
    }

    /** $deprecated */
    private function fixedParams(array $zones): array
    {
        return array_map(
            function ($key) {
                return json_decode(
                    <<<"JSON"
{
    "banner_filters": {
        "require": [],
        "exclude": []
    },
    "keywords": {},
    "banner_size": "300x300",
    "publisher_id": "321",
    "request_id": {$key},
    "user_id": "{$this->user['uid']}"
}
JSON
                    ,
                    true
                );
            },
            array_keys($zones)
        );
    }

    public function keywords()
    {
        return $this->site['keywords'];
    }
}
