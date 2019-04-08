<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

use Adshares\Adserver\Models\Regulation;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;

final class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    private const URI_TERMS = '/admin/terms';

    private const URI_PRIVACY_POLICY = '/admin/privacy';

    private const REGULATION_RESPONSE_STRUCTURE = [
        'content',
    ];

    public function testTermsGetWhileEmpty(): void
    {
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');

        $response = $this->getJson(self::URI_TERMS);
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testTermsGet(): void
    {
        Regulation::addTerms('old content');
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');

        $response = $this->getJson(self::URI_TERMS);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::REGULATION_RESPONSE_STRUCTURE);

        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('content', $decodedResponse);
        $this->assertEquals('old content', $decodedResponse['content']);
    }

    public function testTermsUpdate(): void
    {
        Regulation::addTerms('old content');
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');

        $data = ['content' => 'content'];

        $response = $this->putJson(self::URI_TERMS, $data);
        $response->assertStatus(Response::HTTP_NO_CONTENT);
    }

    public function testTermsUpdateByUnauthorizedUser(): void
    {
        $this->actingAs(factory(User::class)->create(['is_admin' => 0]), 'api');

        $data = ['content' => 'content'];

        $response = $this->putJson(self::URI_TERMS, $data);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testPrivacyPolicyGetWhileEmpty(): void
    {
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');

        $response = $this->getJson(self::URI_PRIVACY_POLICY);
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testPrivacyPolicyGet(): void
    {
        Regulation::addPrivacyPolicy('old content');
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');

        $response = $this->getJson(self::URI_PRIVACY_POLICY);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::REGULATION_RESPONSE_STRUCTURE);

        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('content', $decodedResponse);
        $this->assertEquals('old content', $decodedResponse['content']);
    }

    public function testPrivacyPolicyUpdate(): void
    {
        Regulation::addPrivacyPolicy('old content');
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');

        $data = ['content' => 'content'];

        $response = $this->putJson(self::URI_PRIVACY_POLICY, $data);
        $response->assertStatus(Response::HTTP_NO_CONTENT);
    }

    public function testPrivacyPolicyUpdateByUnauthorizedUser(): void
    {
        $this->actingAs(factory(User::class)->create(['is_admin' => 0]), 'api');

        $data = ['content' => 'content'];

        $response = $this->putJson(self::URI_PRIVACY_POLICY, $data);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }
}
