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

use Adshares\Adserver\Models\SspHost;
use Adshares\Common\Domain\ValueObject\AccountId;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SspHost>
 */
class SspHostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ads_address' => (new AccountId('0001-00000001-8B4E'))->toString(),
        ];
    }
}
