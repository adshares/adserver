<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

use Adshares\Adserver\Models\Campaign;
use Faker\Generator as Faker;

$factory->define(Campaign::class, function (Faker $faker) {
    return [
        'landing_url' => $faker->url,
        'time_start' => $faker->dateTimeThisMonth()->format(DATE_ATOM),
        'status' => Campaign::STATUS_DRAFT,
        'name' => $faker->word,
        'max_cpc' => '200000000000',
        'max_cpm' => '100000000000',
        'budget' => 10000000000000,
        'targeting_excludes' => [],
        'targeting_requires' => [],
        'classification_status' => 0,
        'classification_tags' => null,
        'bid_strategy_uuid' => '00000000000000000000000000000000',
    ];
});
