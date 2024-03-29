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

use Adshares\Adserver\Models\RefLink;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Http\Response;

class RefLinksControllerTest extends TestCase
{
    private const URI = '/api/ref-links';

    public function testRefLinkInfoWhenRefLinkIsNotFound(): void
    {
        $response = $this->getJson(self::URI . '/info/dummy-token');
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testRefLinkInfo(): void
    {
        RefLink::factory()->create(
            [
                'token' => 'my-token',
                'valid_until' => null,
                'single_use' => false,
            ]
        );

        $response = $this->getJson(self::URI . '/info/my-token');
        $response->assertStatus(Response::HTTP_OK);
        $data = $response->json();

        $this->assertEquals(['token', 'status'], array_keys($data));
        $this->assertEquals('my-token', $data['token']);
        $this->assertEquals(RefLink::STATUS_ACTIVE, $data['status']);
    }

    public function testOutDatedRefLinkInfo(): void
    {
        RefLink::factory()->create(
            [
                'token' => 'my-token',
                'valid_until' => '2020-01-01 01:00:00',
            ]
        );

        $response = $this->getJson(self::URI . '/info/my-token');
        $response->assertStatus(Response::HTTP_OK);
        $data = $response->json();

        $this->assertEquals('my-token', $data['token']);
        $this->assertEquals(RefLink::STATUS_OUTDATED, $data['status']);
    }

    public function testUnusedRefLinkInfo(): void
    {
        RefLink::factory()->create(
            [
                'token' => 'my-token',
                'single_use' => true,
                'used' => false,
            ]
        );

        $response = $this->getJson(self::URI . '/info/my-token');
        $response->assertStatus(Response::HTTP_OK);
        $data = $response->json();

        $this->assertEquals('my-token', $data['token']);
        $this->assertEquals(RefLink::STATUS_ACTIVE, $data['status']);
    }

    public function testUsedRefLinkInfo(): void
    {
        RefLink::factory()->create(
            [
                'token' => 'my-token',
                'single_use' => true,
                'used' => true,
            ]
        );

        $response = $this->getJson(self::URI . '/info/my-token');
        $response->assertStatus(Response::HTTP_OK);
        $data = $response->json();

        $this->assertEquals('my-token', $data['token']);
        $this->assertEquals(RefLink::STATUS_USED, $data['status']);
    }

    public function testBrowseRefLinksWhenNoRefLinks(): void
    {
        $this->login();

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(0, 'data');
    }

    public function testBrowseRefLinks(): void
    {
        $user = $this->login();

        RefLink::factory()->create(
            [
                'user_id' => $user->id,
                'token' => 'my-token',
                'comment' => 'test comment',
                'valid_until' => '2021-01-01 01:00:00',
                'single_use' => true,
                'bonus' => 100,
                'refund' => 0.5,
                'kept_refund' => 0.25,
                'refund_valid_until' => '2021-02-01T02:00:00Z',
            ]
        );
        // default ref link
        RefLink::factory()->create(['user_id' => $user->id]);
        // outdated ref link
        RefLink::factory()->create(['user_id' => $user->id, 'valid_until' => now()->subDay()]);
        // used ref link
        RefLink::factory()->create(['user_id' => $user->id, 'single_use' => true, 'used' => true]);
        // deleted ref link
        RefLink::factory()->create(['user_id' => $user->id, 'deleted_at' => now()]);
        // other user ref link
        RefLink::factory()->create();

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'currentPage',
            'data' => [
                '*' => [
                    'bonus',
                    'comment',
                    'createdAt',
                    'deletedAt',
                    'id',
                    'keptRefund',
                    'refund',
                    'refundValidUntil',
                    'refunded',
                    'singleUse',
                    'token',
                    'updatedAt',
                    'usageCount',
                    'used',
                    'userId',
                    'userRoles',
                    'validUntil',
                ],
            ],
            'firstPageUrl',
            'from',
            'lastPage',
            'lastPageUrl',
            'links' => [
                '*' => [
                    'active',
                    'label',
                    'url',
                ],
            ],
            'nextPageUrl',
            'path',
            'perPage',
            'prevPageUrl',
            'to',
            'total',
        ]);
        $response->assertJsonCount(4, 'data');
        $data = $response->json('data')[3];

        $this->assertEquals('my-token', $data['token']);
        $this->assertEquals('test comment', $data['comment']);
        $this->assertEquals('2021-01-01T01:00:00+00:00', $data['validUntil']);
        $this->assertEquals(true, $data['singleUse']);
        $this->assertEquals(false, $data['used']);
        $this->assertEquals(0, $data['usageCount']);
        $this->assertEquals(100, $data['bonus']);
        $this->assertEquals(0.5, $data['refund']);
        $this->assertEquals(0.25, $data['keptRefund']);
        $this->assertEquals(0, $data['refunded']);
        $this->assertEquals('2021-02-01T02:00:00+00:00', $data['refundValidUntil']);
    }

    public function testBrowseRefLinksWithUsage(): void
    {
        $user = $this->login();

        $refLink = RefLink::factory()->create(['user_id' => $user->id]);

        User::factory()->create(['ref_link_id' => $refLink->id]);
        User::factory()->create(['ref_link_id' => $refLink->id]);

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $data = $response->json('data')[0];

        $this->assertEquals(2, $data['usageCount']);
    }

    public function testBrowseRefLinksWithRefund(): void
    {
        $user = $this->login();

        $refLink = RefLink::factory()->create(['user_id' => $user->id]);

        UserLedgerEntry::factory()->create(
            [
                'user_id' => $user->id,
                'type' => UserLedgerEntry::TYPE_REFUND,
                'ref_link_id' => $refLink->id,
                'amount' => 1000,
            ]
        );
        UserLedgerEntry::factory()->create(
            [
                'user_id' => $user->id,
                'type' => UserLedgerEntry::TYPE_REFUND,
                'ref_link_id' => $refLink->id,
                'amount' => 200,
            ]
        );
        UserLedgerEntry::factory()->create(
            [
                'type' => UserLedgerEntry::TYPE_BONUS_INCOME,
                'ref_link_id' => $refLink->id,
                'amount' => 2000,
            ]
        );

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1, 'data');
        $data = $response->json('data')[0];

        $this->assertEquals(1200, $data['refunded']);
    }

    public function testAddRefLink(): void
    {
        $this->login();

        $response = $this->postJson(self::URI, []);
        $response->assertStatus(Response::HTTP_CREATED);

        $response = $this->postJson(self::URI, ['refLink' => []]);
        $response->assertStatus(Response::HTTP_CREATED);

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2, 'data');
        $data1 = $response->json('data')[0];
        $data2 = $response->json('data')[1];

        $this->assertNull($data1['comment']);
        $this->assertEquals(1, $data1['keptRefund']);
        $this->assertNotEquals($data1['token'], $data2['token']);
    }

    public function testAddRefLinkWithCustomAttributes(): void
    {
        $this->login();
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
        $response->assertJsonCount(1, 'data');
        $data = $response->json('data')[0];

        $this->assertEquals('dummy-token', $data['token']);
        $this->assertEquals('test comment', $data['comment']);
        $this->assertEquals(0.25, $data['keptRefund']);
    }

    public function testAddRefLinkWithDuplicatedToken(): void
    {
        $this->login();

        RefLink::factory()->create(['token' => 'dummy-token']);

        $response = $this->postJson(self::URI, ['refLink' => ['token' => 'dummy-token']]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $data = $response->json();

        $this->assertEquals('The token has already been taken.', $data['errors']['token'][0]);
    }

    public function testAddRefLinkWithDuplicatedDeletedToken(): void
    {
        $user = $this->login();

        RefLink::factory()->create(['user_id' => $user->id, 'token' => 'dummy-token', 'deleted_at' => now()]);

        $response = $this->postJson(self::URI, ['refLink' => ['token' => 'dummy-token']]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $data = $response->json();

        $this->assertEquals('The token has already been taken.', $data['errors']['token'][0]);
    }

    public function testAddRefLinkWithForbiddenAttributes(): void
    {
        $this->login();

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
        $this->login(User::factory()->admin()->create());

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
                    'keptRefund' => 0.25,
                    'refundValidUntil' => '2021-02-01T02:00:00Z',
                ]
            ]
        );
        $response->assertStatus(Response::HTTP_CREATED);

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);

        $response->assertJsonCount(1, 'data');
        $data = $response->json('data')[0];

        $this->assertEquals('dummy-token', $data['token']);
        $this->assertEquals('test comment', $data['comment']);
        $this->assertEquals('2021-01-01T01:00:00+00:00', $data['validUntil']);
        $this->assertEquals(true, $data['singleUse']);
        $this->assertEquals(100, $data['bonus']);
        $this->assertEquals(0.5, $data['refund']);
        $this->assertEquals(0.25, $data['keptRefund']);
        $this->assertEquals('2021-02-01T02:00:00+00:00', $data['refundValidUntil']);
    }

    public function testAddRefLinkValidation(): void
    {
        $this->login(User::factory()->admin()->create());

        $response = $this->postJson(
            self::URI,
            [
                'refLink' => [
                    'token' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                    'validUntil' => 'foo_date',
                    'singleUse' => 'invalid bool',
                    'bonus' => -100,
                    'refund' => 15,
                    'keptRefund' => -0.5,
                    'refundValidUntil' => 'foo_date+time',
                ]
            ]
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $errors = $response->json()['errors'];
        $this->assertArrayHasKey('token', $errors);
        $this->assertArrayHasKey('validUntil', $errors);
        $this->assertArrayHasKey('singleUse', $errors);
        $this->assertArrayHasKey('bonus', $errors);
        $this->assertArrayHasKey('refund', $errors);
        $this->assertArrayHasKey('keptRefund', $errors);
        $this->assertArrayHasKey('refundValidUntil', $errors);
    }

    public function testDeleteUsedRefLink(): void
    {
        $user = $this->login();
        /** @var RefLink $refLink */
        $refLink = RefLink::factory()->create(
            [
                'used' => true,
                'user_id' => $user->id,
            ]
        );

        $response = $this->delete(self::buildDeleteUri($refLink->id));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $refLink->refresh();
        self::assertNull($refLink->deleted_at);
    }

    public function testDeleteOtherUserRefLink(): void
    {
        $this->login();
        /** @var RefLink $refLink */
        $refLink = RefLink::factory()->create(['used' => false]);

        $response = $this->delete(self::buildDeleteUri($refLink->id));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
        $refLink->refresh();
        self::assertNull($refLink->deleted_at);
    }

    public function testDeleteNotUsedRefLink(): void
    {
        $user = $this->login();
        /** @var RefLink $refLink */
        $refLink = RefLink::factory()->create(
            [
                'used' => false,
                'user_id' => $user->id,
            ]
        );

        $response = $this->delete(self::buildDeleteUri($refLink->id));

        $response->assertStatus(Response::HTTP_OK);
        $refLink->refresh();
        self::assertNotNull($refLink->deleted_at);
    }

    private static function buildDeleteUri(int $refLinkId): string
    {
        return sprintf('%s/%d', self::URI, $refLinkId);
    }
}
