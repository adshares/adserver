<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

declare(strict_types=1);

namespace Database\Factories;

use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserLedgerEntryFactory extends Factory
{
    public function definition(): array
    {
        $addresses = [
            '0001-00000001-8B4E',
            '0001-00000002-BB2D',
            '0001-00000003-AB0C',
            '0001-00000004-DBEB',
            '0001-00000005-CBCA',
            '0001-00000006-FBA9',
        ];

        return [
            'user_id' => User::factory(),
            'amount' => $this->faker->numberBetween(0, 3800000000000000000),
            'status' => UserLedgerEntry::STATUS_ACCEPTED,
            'type' => UserLedgerEntry::TYPE_DEPOSIT,
            'address_from' => $this->faker->randomElement($addresses),
            'address_to' => $this->faker->randomElement($addresses),
            'txid' => $this->faker->regexify('[0-9A-F]{4}:[0-9A-F]{8}:[0-9A-F]{4}'),
        ];
    }
}
