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

use Adshares\Adserver\Models\NetworkEventLog;
use Adshares\Common\Domain\ValueObject\AccountId;
use Faker\Generator as Faker;

$factory->define(
    NetworkEventLog::class,
    function (Faker $faker) {
        $addresses = [
            AccountId::fromIncompleteString('0001-00000001'),
            AccountId::fromIncompleteString('0001-00000002'),
            AccountId::fromIncompleteString('0001-00000003'),
            AccountId::fromIncompleteString('0001-00000004'),
            AccountId::fromIncompleteString('0001-00000005'),
            AccountId::fromIncompleteString('0001-00000006'),
            AccountId::fromIncompleteString('0001-00000007'),
            AccountId::fromIncompleteString('0001-00000008'),
        ];

        return [
            'case_id' => $faker->uuid,
            'event_id' => $faker->uuid,
            'user_id' => $faker->uuid,
            'banner_id' => $faker->uuid,
            'zone_id' => $faker->uuid,
            'publisher_id' => $faker->uuid,
            'site_id' => $faker->uuid,
            'event_type' => $faker->randomElement(['serve', 'view', 'click']),
            'ip' => bin2hex(inet_pton($faker->ipv4)),
            'event_value' => $faker->numberBetween(10 ** 4, 10 ** 7),
            'pay_from' => $faker->randomElement($addresses),
            'headers' => <<<JSON
{
    "host": [
        "{$faker->ipv4}"
    ],
    "accept": [
        "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8"
    ],
    "cookie": [
        "tid=UaBp3Jjxnc-A4vORitTMXBYZuF268Q; io=QzTM0GfPPsUvjM0SAAAH"
    ],
    "referer": [
        "http://localhost:8000/Page2/"
    ],
    "connection": [
        "keep-alive"
    ],
    "user-agent": [
        "{$faker->chrome}"
    ],
    "content-type": [
        ""
    ],
    "content-length": [
        ""
    ],
    "accept-encoding": [
        "gzip, deflate"
    ],
    "accept-language": [
        "pl,en-US;q=0.7,en;q=0.3"
    ]
}
JSON
            ,
        ];
    }
);
