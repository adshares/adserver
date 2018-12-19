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

use Adshares\Adserver\Models\EventLog;
use Adshares\Common\Domain\ValueObject\AccountId;
use Faker\Generator as Faker;

$factory->define(
    EventLog::class,
    function (Faker $faker) {
        return [
            'case_id' => $faker->uuid,
            'event_id' => $faker->uuid,
            'user_id' => $faker->uuid,
            'banner_id' => $faker->uuid,
            'publisher_id' => $faker->uuid,
            'event_type' => $faker->randomElement(['serve', 'view', 'click']),
            'ip' => bin2hex(inet_pton($faker->ipv4)),
            'event_value' => $faker->numberBetween(0, 10 ** 5),
            'pay_to' => AccountId::fromIncompleteString($faker->regexify('[0-9A-F]{4}-[0-9A-F]{8}')),
        ];
    }
);
