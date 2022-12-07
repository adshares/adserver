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
use Adshares\Adserver\ViewModel\ScopeType;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;

final class ApiTaxonomyControllerTest extends TestCase
{
    public function testMedia(): void
    {
        $this->setUpUser();

        $response = self::get('/api/v2/taxonomy/media');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertExactJson(['data' => ['web' => 'Website', 'metaverse' => 'Metaverse']]);
    }

    public function testMedium(): void
    {
        $this->setUpUser();

        $response = self::get('/api/v2/taxonomy/media/web');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['data']);
        $response->assertJsonFragment(['name' => 'web', 'label' => 'Website']);
        $response->assertJsonFragment(['apple-os' => 'Apple OS']);
    }

    public function testVendors(): void
    {
        $this->setUpUser();

        $response = self::get('/api/v2/taxonomy/media/web/vendors');
        $response->assertStatus(Response::HTTP_OK);
        $response->assertContent('{"data":{}}');
    }

    private function setUpUser(): User
    {
        /** @var User $user */
        $user = User::factory()->create();
        Passport::actingAs($user, [ScopeType::CAMPAIGN_READ], 'jwt');
        return $user;
    }
}
