<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail,
            'password' => $this->faker->password(8),
            'uuid' => $this->faker->md5,
            'is_advertiser' => 1,
            'is_publisher' => 1,
            'is_admin' => false,
            'invalid_login_attempts' => 0,
        ];
    }

    public function admin(): self
    {
        return $this->state([
            'admin_confirmed_at' => new DateTimeImmutable('-10 days'),
            'email_confirmed_at' => new DateTimeImmutable('-10 days'),
            'is_admin' => true,
        ]);
    }
}
