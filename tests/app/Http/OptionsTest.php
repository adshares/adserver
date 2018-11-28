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
declare(strict_types = 1);

namespace Adshares\Adserver\Tests\Http;

use Adshares\Adserver\Client\DummyAdClassifyClient;
use Adshares\Adserver\Client\DummyAdUserClient;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Service\FilteringOptionsSource;
use Adshares\Common\Application\Service\TargetingOptionsSource;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OptionsTest extends TestCase
{
    use RefreshDatabase;

    public function testTargeting(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->getJson('/api/options/campaigns/targeting');
        $response->assertStatus(200)
            ->assertJsonStructure(
                [
                    '*' => [
                        'key',
                        'label',
                    ],
                ]
            );

        $content = json_decode($response->content(), true);
        $this->assertStructure($content);
    }

    private function assertStructure(array $content): void
    {
        foreach ($content as $item) {
            if ($item['children'] ?? false) {
                self::assertNotEmpty($item['children']);
                self::assertFalse($item['values'] ?? false);
                self::assertFalse($item['valueType'] ?? false);
                self::assertFalse($item['allowInput'] ?? false);
            } else {
                self::assertInternalType('array', $item['values'] ?? []);
                self::assertInternalType('string', $item['valueType']);
                self::assertInternalType('bool', $item['allowInput']);
            }
            self::assertInternalType('string', $item['key']);
            self::assertInternalType('string', $item['label']);
        }
    }

    public function testFiltering(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->getJson('/api/options/sites/filtering');
        $response->assertStatus(200)
            ->assertJsonStructure(
                [
                    '*' => [
                        'key',
                        'label',
                    ],
                ]
            );

        $content = json_decode($response->content(), true);
        $this->assertStructure($content);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(TargetingOptionsSource::class, function () {
            return new DummyAdUserClient();
        });

        $this->app->bind(FilteringOptionsSource::class, function () {
            return new DummyAdClassifyClient();
        });
    }
}
