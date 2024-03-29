<?php

namespace Database\Factories;

use Adshares\Adserver\Models\NotificationEmailLog;
use Adshares\Adserver\Models\User;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationEmailLog>
 */
class NotificationEmailLogFactory extends Factory
{
    private const MAXIMAL_TIMESTAMP = '@2147483647';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => '',
            'created_at' => new DateTimeImmutable(),
            'properties' => [],
            'user_id' => User::factory()->create(),
            'valid_until' => new DateTimeImmutable(self::MAXIMAL_TIMESTAMP),
        ];
    }
}
