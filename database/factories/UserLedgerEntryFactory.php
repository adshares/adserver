<?php
/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Common\Domain\ValueObject\AccountId;
use Faker\Generator as Faker;

$factory->define(UserLedgerEntry::class, function (Faker $faker) {
    return [
        'user_id' => function() {
            return factory(User::class)->create()->id;
        },
        'amount' => $faker->numberBetween(0, 3800000000000000000),
        'status' => UserLedgerEntry::STATUS_ACCEPTED,
        'type' => UserLedgerEntry::TYPE_DEPOSIT,
        'address_from' => AccountId::fromIncompleteString($faker->regexify('[0-9A-F]{4}-[0-9A-F]{8}')),
        'address_to' => AccountId::fromIncompleteString($faker->regexify('[0-9A-F]{4}-[0-9A-F]{8}')),
        'txid' => $faker->regexify('[0-9A-F]{4}:[0-9A-F]{8}:[0-9A-F]{4}'),
    ];
});
