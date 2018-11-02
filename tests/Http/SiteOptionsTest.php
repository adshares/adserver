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

namespace Adshares\Adserver\Tests\Http;

use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SiteOptionsTest extends TestCase
{
    use RefreshDatabase;
    const URI = '/api/options/sites';

    public function testLanguages(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->getJson(self::URI . '/languages');
        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'name',
                    'code',
                ],
            ]);
    }

    public function testZones(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->getJson(self::URI . '/zones');
        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'name',
                    'type',
                    'size',
                    'tags' => [],
                    'width',
                    'height',
                ],
            ]);
    }

    public function testFiltering(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->getJson(self::URI . '/filtering');
        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'label',
                    'key',
                    'values' => ['*' => ['label', 'value']],
                    'valueType',
                    'allowInput',
                ],
            ]);
    }
}
