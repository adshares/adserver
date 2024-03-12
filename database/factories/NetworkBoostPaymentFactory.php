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

declare(strict_types=1);

namespace Database\Factories;

use Adshares\Adserver\Models\NetworkBoostPayment;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NetworkBoostPayment>
 */
class NetworkBoostPaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pay_time' => new DateTimeImmutable(),
            'total_amount' => 100_000_000_000,
            'license_fee' => 0,
            'operator_fee' => 0,
            'paid_amount' => 100_000_000_000,
            'exchange_rate' => 1,
            'paid_amount_currency' => 100_000_000_000,
        ];
    }
}
