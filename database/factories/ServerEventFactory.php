<?php

namespace Database\Factories;

use Adshares\Adserver\Models\ServerEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServerEvent>
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
