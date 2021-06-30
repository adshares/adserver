<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

namespace Adshares\Tests\Common;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Feature;

use function factory;

class FeatureTest extends TestCase
{
    private const FEATURE_KEY = 'feature';

    public function testEnabled(): void
    {
        factory(Config::class)->create([
            'key' => self::FEATURE_KEY . '-enabled',
            'value' => '1',
        ]);

        self::assertTrue(Feature::enabled(self::FEATURE_KEY));
    }

    public function testEnabledNotInDatabase(): void
    {
        self::assertFalse(Feature::enabled(self::FEATURE_KEY));
    }

    public function testEnabledFalse(): void
    {
        factory(Config::class)->create([
            'key' => self::FEATURE_KEY . '-enabled',
            'value' => '0',
        ]);

        self::assertFalse(Feature::enabled(self::FEATURE_KEY));
    }
}
