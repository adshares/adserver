<?php

namespace Database\Factories;

use Adshares\Adserver\Models\ServerEventLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServerEventLog>
 */
class ServerEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'properties' => [],
            'type' => 'test',
        ];
    }
}
