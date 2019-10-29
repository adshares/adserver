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

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\EventLog;
use Adshares\Common\Domain\ValueObject\AccountId;
use Faker\Generator as Faker;

$factory->define(
    EventLog::class,
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

        $caseId = $faker->uuid;
        $eventType = $faker->randomElement([EventLog::TYPE_VIEW, EventLog::TYPE_CLICK]);

        return [
            'case_id' => $caseId,
            'event_id' => Utils::createCaseIdContainingEventType($caseId, $eventType),
            'user_id' => $faker->uuid,
            'tracking_id' => $faker->uuid,
            'banner_id' => $faker->uuid,
            'publisher_id' => $faker->uuid,
            'advertiser_id' => $faker->uuid,
            'campaign_id' => $faker->uuid,
            'zone_id' => $faker->uuid,
            'event_type' => $eventType,
            'event_value_currency' => $faker->numberBetween(10 ** 4, 10 ** 7),
            'exchange_rate' => null,
            'event_value' => null,
            'pay_to' => $faker->randomElement($addresses),
            'payment_status' => 0,
            'their_context' => [
                'site' => [
                    'domain' => 'example.com',
                    'inframe' => 'no',
                    'page' => 'http://example.com/test.html',
                    'keywords' => [0 => '',],
                    'referrer' => '',
                    'popup' => 0,
                ],
                'device' => [
                    'ua' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:68.0) Gecko/20100101 Firefox/68.0',
                    'ip' => '172.25.0.1',
                    'ips' => [0 => '172.25.0.1',],
                    'headers' => [
                        'upgrade-insecure-requests' => [0 => '1',],
                        'cookie' => [0 => 'tid=T1N2PWZTZ0tth-rxeVHxmY4bbKJJCg',],
                        'connection' => [0 => 'keep-alive',],
                        'referer' => [0 => 'http://example.com/test.html',],
                        'accept-encoding' => [0 => 'gzip, deflate',],
                        'accept-language' => [0 => 'en-US,en;q=0.5',],
                        'accept' => [0 => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',],
                        'user-agent' => [
                            0 => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:68.0) Gecko/20100101 Firefox/68.0',
                        ],
                        'host' => [0 => 'example.com',],
                        'content-length' => [0 => '',],
                        'content-type' => [0 => '',],
                    ],
                ],
            ],
        ];
    }
);
