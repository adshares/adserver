<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Http\Controllers\Manager;

use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Mock\Repository\DummyConfigurationRepository;

final class OptionsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(
            ConfigurationRepository::class,
            static function () {
                return new DummyConfigurationRepository();
            }
        );
    }

    public function testMedia(): void
    {
        self::actingAs(factory(User::class)->create(), 'api');

        $response = self::get('/api/options/campaigns/media');
        $response->assertStatus(200);
        $response->assertJson(['web' => 'Website', 'metaverse' => 'Metaverse']);
    }

    public function testMedium(): void
    {
        self::actingAs(factory(User::class)->create(), 'api');

        $response = self::get('/api/options/campaigns/media/web');
        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'web', 'label' => 'Website']);
        $response->assertJsonFragment(['apple-os' => 'Apple OS']);
    }

    public function testMediumExcludeQuality(): void
    {
        self::actingAs(factory(User::class)->create(), 'api');

        $response = self::get('/api/options/campaigns/media/web?e=1');
        $response->assertStatus(200);
        $response->assertJsonMissing(['label' => 'Quality', 'name' => 'quality']);
    }
}
