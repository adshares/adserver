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

namespace Adshares\Adserver\Tests\Http\Controllers\Manager;

use Adshares\Adserver\Models\RefLink;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Http\Response;

class RefLinksControllerTest extends TestCase
{
    private const URI = '/api/ref-links';

    public function testBrowseRefLinksWhenNoRefLinks(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(0);
    }

    public function testBrowseRefLinks(): void
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        factory(RefLink::class)->create(
            [
                'user_id' => $user->id,
                'token' => 'dummy-token',
                'comment' => 'test comment',
                'valid_until' => '2021-01-01 01:00:00',
                'single_use' => true,
                'bonus' => 100,
                'refund' => 0.5,
                'kept_refund' => 0.25,
                'refund_valid_until' => '2021-02-01 02:00:00',
            ]
        );
        // default ref link
        factory(RefLink::class)->create(['user_id' => $user->id]);
        // outdated ref link
        factory(RefLink::class)->create(['user_id' => $user->id, 'valid_until' => now()->subDay()]);
        // used ref link
        factory(RefLink::class)->create(['user_id' => $user->id, 'single_use' => true, 'used' => true]);
        // deleted ref link
        factory(RefLink::class)->create(['user_id' => $user->id, 'deleted_at' => now()]);
        // other user ref link
        factory(RefLink::class)->create();

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(4);
        $data = $response->json()[0];

        $this->assertEquals('dummy-token', $data['token']);
        $this->assertEquals('test comment', $data['comment']);
        $this->assertEquals('2021-01-01 01:00:00', $data['validUntil']);
        $this->assertEquals(true, $data['singleUse']);
        $this->assertEquals(false, $data['used']);
        $this->assertEquals(0, $data['usageCount']);
        $this->assertEquals(100, $data['bonus']);
        $this->assertEquals(0.5, $data['refund']);
        $this->assertEquals(0.25, $data['keptRefund']);
        $this->assertEquals('2021-02-01 02:00:00', $data['refundValidUntil']);
    }

    public function testBrowseRefLinksWithUsage(): void
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        $refLink = factory(RefLink::class)->create(['user_id' => $user->id]);

        factory(User::class)->create(['ref_link_id' => $refLink->id]);
        factory(User::class)->create(['ref_link_id' => $refLink->id]);

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1);
        $data = $response->json()[0];

        $this->assertEquals(2, $data['usageCount']);
    }

    public function testAddRefLink(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->postJson(self::URI, []);
        $response->assertStatus(Response::HTTP_CREATED);

        $response = $this->postJson(self::URI, ['refLink' => []]);
        $response->assertStatus(Response::HTTP_CREATED);

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2);
        $data1 = $response->json()[0];
        $data2 = $response->json()[1];

        $this->assertNull($data1['comment']);
        $this->assertEquals(1, $data1['keptRefund']);
        $this->assertNotEquals($data1['token'], $data2['token']);
    }

    public function testAddRefLinkWithCustomAttributes(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');
        $response = $this->postJson(
            self::URI,
            [
                'refLink' => [
                    'token' => 'dummy-token',
                    'comment' => 'test comment',
                    'keptRefund' => 0.25,
                ]
            ]
        );
        $response->assertStatus(Response::HTTP_CREATED);

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1);
        $data = $response->json()[0];

        $this->assertEquals('dummy-token', $data['token']);
        $this->assertEquals('test comment', $data['comment']);
        $this->assertEquals(0.25, $data['keptRefund']);
    }

    public function testAddRefLinkWithDuplicatedToken(): void
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        factory(RefLink::class)->create(['token' => 'dummy-token']);

        $response = $this->postJson(self::URI, ['refLink' => ['token' => 'dummy-token']]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $data = $response->json();

        $this->assertEquals('The token has already been taken.', $data['errors']['token'][0]);
    }

    public function testAddRefLinkWithDuplicatedDeletedToken(): void
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        factory(RefLink::class)->create(['user_id' => $user->id, 'token' => 'dummy-token', 'deleted_at' => now()]);

        $response = $this->postJson(self::URI, ['refLink' => ['token' => 'dummy-token']]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $data = $response->json();

        $this->assertEquals('The token has already been taken.', $data['errors']['token'][0]);
    }

    public function testAddRefLinkWithForbiddenAttributes(): void
    {
        $this->actingAs(factory(User::class)->create(['is_admin' => false]), 'api');

        $response = $this->postJson(self::URI, ['refLink' => ['validUntil' => '2021-01-01 01:00:00']]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->postJson(self::URI, ['refLink' => ['singleUse' => true]]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->postJson(self::URI, ['refLink' => ['bonus' => 100]]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->postJson(self::URI, ['refLink' => ['refund' => 0.5]]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response = $this->postJson(self::URI, ['refLink' => ['refundValidUntil' => '2021-02-01 02:00:00']]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testAddRefLinkAsAdmin(): void
    {
        $this->actingAs(factory(User::class)->create(['is_admin' => true]), 'api');

        $response = $this->postJson(
            self::URI,
            [
                'refLink' => [
                    'token' => 'dummy-token',
                    'comment' => 'test comment',
                    'validUntil' => '2021-01-01 01:00:00',
                    'singleUse' => true,
                    'bonus' => 100,
                    'refund' => 0.5,
                    'kept_refund' => 0.25,
                    'refundValidUntil' => '2021-02-01 02:00:00',
                ]
            ]
        );
        $response->assertStatus(Response::HTTP_CREATED);

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1);
        $data = $response->json()[0];

        $this->assertEquals('dummy-token', $data['token']);
        $this->assertEquals('test comment', $data['comment']);
        $this->assertEquals('2021-01-01 01:00:00', $data['validUntil']);
        $this->assertEquals(true, $data['singleUse']);
        $this->assertEquals(100, $data['bonus']);
        $this->assertEquals(0.5, $data['refund']);
        $this->assertEquals(0.25, $data['keptRefund']);
        $this->assertEquals('2021-02-01 02:00:00', $data['refundValidUntil']);
    }
}