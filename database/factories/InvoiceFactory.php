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

use Adshares\Adserver\Models\Invoice;
use Adshares\Adserver\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $issuedDate = new Carbon($this->faker->date());
        $netAmount = $this->faker->randomFloat(2, 0, 9999999);
        $vatAmount = $netAmount * 0.23;

        return [
            'user_id' => User::factory(),
            'type' => Invoice::TYPE_PROFORMA,
            'number' => $this->faker->unique()->numerify('PROF ###/##/####'),
            'issue_date' => $issuedDate,
            'due_date' => $issuedDate->addDays(14),
            'seller_name' => $this->faker->company,
            'seller_address' => $this->faker->address,
            'seller_postal_code' => $this->faker->postcode,
            'seller_city' => $this->faker->city,
            'seller_country' => $this->faker->countryCode,
            'seller_vat_id' => $this->faker->numerify('VAT#########'),
            'buyer_name' => $this->faker->company,
            'buyer_address' => $this->faker->address,
            'buyer_postal_code' => $this->faker->optional()->postcode,
            'buyer_city' => $this->faker->optional()->city,
            'buyer_country' => $this->faker->countryCode,
            'buyer_vat_id' => $this->faker->numerify('VAT#########'),
            'currency' => $this->faker->countryCode,
            'net_amount' => $netAmount,
            'vat_amount' => $vatAmount,
            'gross_amount' => $netAmount + $vatAmount,
            'vat_rate' => '23%',
            'comments' => $this->faker->optional()->text,
            'html_output' => '<h1>TEST</h1>',
        ];
    }
}
