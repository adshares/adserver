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

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

class NetworkCasePaymentFactory extends Factory
{
    public function definition(): array
    {
        $paidAmount = $this->faker->randomNumber();
        $operatorFee = $this->faker->numberBetween(0, $paidAmount);
        $licenseFee = $this->faker->numberBetween(0, $paidAmount - $operatorFee);

        return [
            'network_case_id' => $this->faker->randomNumber(),
            'created_at' => new DateTimeImmutable(),
            'pay_time' => new DateTimeImmutable(),
            'ads_payment_id' => $this->faker->randomNumber(),
            'total_amount' => $paidAmount + $operatorFee + $licenseFee,
            'license_fee' => $licenseFee,
            'operator_fee' => $operatorFee,
            'paid_amount' => $paidAmount,
            'exchange_rate' => 5.0,
            'paid_amount_currency' => (int)floor($paidAmount * 5.0),
        ];
    }
}
