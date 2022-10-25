<?php

namespace Database\Factories;

use Adshares\Adserver\Models\ServerEventLog;
use Adshares\Adserver\ViewModel\ServerEventType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServerEventLog>
 */
class ServerEventLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'properties' => [],
            'type' => $this->faker->randomElement(ServerEventType::cases()),
        ];
    }
}
