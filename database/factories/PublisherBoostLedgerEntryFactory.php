<?php

/**
 * Copyright (c) 2018-2024 Adshares sp. z o.o.
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

namespace Database\Factories;

use Adshares\Adserver\Models\PublisherBoostLedgerEntry;
use Adshares\Adserver\Models\User;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublisherBoostLedgerEntry>
 */
class PublisherBoostLedgerEntryFactory extends Factory
{
    public function definition(): array
    {
        $now = new DateTimeImmutable();
        return [
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
            'amount' => 100_000_000_000,
            'amount_left' => 100_000_000_000,
            'ads_address' => '0001-00000001-8B4E',
            'user_id' => User::factory()->create(),
        ];
    }
}
