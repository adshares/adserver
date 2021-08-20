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
use Faker\Generator as Faker;

$factory->define(
    \Adshares\Adserver\Models\Invoice::class,
    function (Faker $faker) {
        $issuedDate = new \Illuminate\Support\Carbon($faker->date());
        $netAmount = $faker->randomFloat(2, 0, 9999999);
        $vatAmount = $netAmount * 0.23;
        return [
            'user_id' => function () {
                return factory(User::class)->create()->id;
            },
            'type' => \Adshares\Adserver\Models\Invoice::TYPE_PROFORMA,
            'number' => $faker->unique()->numerify('PROF ###/##/####'),
            'issue_date' => $issuedDate,
            'due_date' => $issuedDate->addDays(14),
            'seller_name' => $faker->company,
            'seller_address' => $faker->address,
            'seller_postal_code' => $faker->postcode,
            'seller_city' => $faker->city,
            'seller_country' => $faker->countryCode,
            'seller_vat_id' => $faker->numerify('VAT#########'),
            'buyer_name' => $faker->company,
            'buyer_address' => $faker->address,
            'buyer_postal_code' => $faker->optional()->postcode,
            'buyer_city' => $faker->optional()->city,
            'buyer_country' => $faker->countryCode,
            'buyer_vat_id' => $faker->numerify('VAT#########'),
            'currency' => $faker->countryCode,
            'net_amount' => $netAmount,
            'vat_amount' => $vatAmount,
            'gross_amount' => $netAmount + $vatAmount,
            'vat_rate' => '23%',
            'comments' => $faker->optional()->text,
            'html_output' => '<h1>TEST</h1>',
        ];
    }
);
