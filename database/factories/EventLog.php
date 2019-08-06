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
            'reason' => 0,
        ];
    }
);
