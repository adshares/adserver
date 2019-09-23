<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

use Adshares\Adserver\Models\NetworkImpression;
use Faker\Generator as Faker;

$factory->define(
    NetworkImpression::class,
    function (Faker $faker) {
        return [
            'impression_id' => $faker->uuid,
            'tracking_id' => $faker->uuid,
            'context' => json_decode(<<<JSON
{
    "site": {
        "domain": "example.com",
        "inframe": "no",
        "page": "http:\/\/example.com\/",
        "keywords": []
    },
    "device": {
        "ua": "Mozilla\/5.0 (X11; Ubuntu; Linux x86_64; rv:64.0) Gecko\/20100101 Firefox\/64.0",
        "ip": "172.21.0.1",
        "ips": [
            "172.21.0.1"
        ],
        "headers": {
            "cookie": [
                "tid=KeU0jaHoz5UsW3VnpQjSbPKQ53zNpw"
            ],
            "connection": [
                "keep-alive"
            ],
            "referer": [
                "http:\/\/example.com\/"
            ],
            "accept-encoding": [
                "gzip, deflate"
            ],
            "accept-language": [
                "en-US,en;q=0.5"
            ],
            "accept": [
                "*\/*"
            ],
            "user-agent": [
                "Mozilla\/5.0 (X11; Ubuntu; Linux x86_64; rv:64.0) Gecko\/20100101 Firefox\/64.0"
            ],
            "host": [
                "example.com"
            ],
            "content-length": [
                ""
            ],
            "content-type": [
                ""
            ]
        }
    },
    "user": {
        "uid": "KeU0jaHoz5UsW3VnpQjSbPKQ53zNpw",
        "keywords": {
            "interest": [
                "200063",
                "200142"
            ],
            "javascript": [
                true
            ],
            "platform": [
                "Ubuntu"
            ],
            "device_type": [
                "Desktop"
            ],
            "browser": [
                "Firefox"
            ],
            "human_score": [
                0.5
            ]
        }
    }
}
JSON
            , true),
        ];
    }
);
