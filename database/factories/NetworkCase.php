<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

use Adshares\Adserver\Models\NetworkCase;
use Faker\Generator as Faker;

$factory->define(
    NetworkCase::class,
    function (Faker $faker) {
        return [
            'case_id' => $faker->uuid,
            'network_impression_id' => 1,
            'publisher_id' => $faker->uuid,
            'site_id' => $faker->uuid,
            'zone_id' => $faker->uuid,
            'domain' => $faker->domainName,
            'campaign_id' => $faker->uuid,
            'banner_id' => $faker->uuid,
        ];
    }
);
