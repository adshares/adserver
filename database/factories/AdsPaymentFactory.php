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

use Adshares\Adserver\Models\AdsPayment;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

class AdsPaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'txid' => '0001:00000083:0001',
            'address' => '0001-00000000-9B6F',
            'amount' => 1e11,
            'status' => AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE,
            'created_at' => new DateTimeImmutable(),
            'updated_at' => new DateTimeImmutable(),
            'tx_time' => new DateTimeImmutable('-8 minutes'),
        ];
    }
}
