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

use Adshares\Adserver\Models\EventBoostLog;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\Uuid;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventBoostLog>
 */
class EventBoostLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'created_at' => new DateTimeImmutable(),
            'updated_at' => new DateTimeImmutable(),
            'computed_at' => new DateTimeImmutable(),
            'advertiser_id' => Uuid::v4()->hex(),
            'campaign_id' => Uuid::v4()->hex(),
            'pay_to' => (new AccountId('0001-00000001-8B4E'))->toString(),
            'event_value_currency' => 0,
            'exchange_rate' => 1,
            'event_value' => 0,
            'license_fee' => 0,
            'operator_fee' => 0,
            'community_fee' => 0,
            'paid_amount' => 0,
            'payment_id' => null,
        ];
    }
}
