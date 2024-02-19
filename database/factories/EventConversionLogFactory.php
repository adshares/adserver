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

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\EventLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventConversionLogFactory extends Factory
{
    public function definition(): array
    {
        $caseId = $this->faker->uuid;

        return [
            'case_id' => $caseId,
            'event_id' => Utils::createCaseIdContainingEventType($caseId, EventLog::TYPE_SHADOW_CLICK),
            'user_id' => $this->faker->uuid,
            'tracking_id' => $this->faker->uuid,
            'banner_id' => $this->faker->uuid,
            'publisher_id' => $this->faker->uuid,
            'advertiser_id' => $this->faker->uuid,
            'campaign_id' => $this->faker->uuid,
            'zone_id' => $this->faker->uuid,
            'event_type' => EventLog::TYPE_SHADOW_CLICK,
        ];
    }
}
