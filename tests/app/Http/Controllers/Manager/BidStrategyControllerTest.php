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

declare(strict_types = 1);

namespace Adshares\Adserver\Tests\Http\Controllers\Manager;

use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BidStrategyControllerTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/campaigns/bid-strategy';
    private const URI_UUID_GET = '/api/campaigns/bid-strategy/uuid-default';
    private const URI_UUID_PUT = '/admin/campaigns/bid-strategy/uuid-default';

    private const STRUCTURE_CHECK = [
        [
            'uuid',
            'name',
            'details' => [
                '*' => [
                    'category',
                    'rank',
                ],
            ],
        ],
    ];

    private const DATA = [
        'name' => 'test-name',
        'details' => [
            [
                'category' => 'user:country:us',
                'rank' => 1,
            ],
            [
                'category' => 'user:country:other',
                'rank' => 0.2,
            ],
        ],
    ];

    public function testInitialBidStrategy(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::STRUCTURE_CHECK);
        $response->assertJsonCount(1);
    }

    public function testAddBidStrategy(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $responsePut = $this->putJson(self::URI, self::DATA);
        $responsePut->assertStatus(Response::HTTP_CREATED);

        $responseGet = $this->getJson(self::URI);
        $responseGet->assertStatus(Response::HTTP_OK);
        $responseGet->assertJsonStructure(self::STRUCTURE_CHECK);
        $responseGet->assertJsonCount(2);

        $content = json_decode($responseGet->getContent(), true);
        $entry = $content[1];
        self::assertEquals(self::DATA['name'], $entry['name']);
        self::assertEquals(self::DATA['details'], $entry['details']);
    }

    public function testEditOwnBidStrategy(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        $bidStrategy = BidStrategy::register('test', $user->id);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->patchJson(self::URI.'/'.$bidStrategyPublicId, self::DATA);
        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $bidStrategyEdited = BidStrategy::fetchByPublicId($bidStrategyPublicId)->toArray();
        self::assertEquals(self::DATA['name'], $bidStrategyEdited['name']);
        self::assertEquals(self::DATA['details'], $bidStrategyEdited['details']);
    }

    public function testEditAlienBidStrategy(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        $bidStrategy = BidStrategy::register('test', $user->id + 1);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->patchJson(self::URI.'/'.$bidStrategyPublicId, self::DATA);
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function testEditNotExistingBidStrategy(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        $bidStrategyPublicId = '0123456789abcdef0123456789abcdef';

        $response = $this->patchJson(self::URI.'/'.$bidStrategyPublicId, self::DATA);
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testEditInvalidBidStrategy(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        $bidStrategyInvalidPublicId = 1000;

        $response = $this->patchJson(self::URI.'/'.$bidStrategyInvalidPublicId, self::DATA);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testDbConnectionErrorWhileAddingBidStrategy(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new RuntimeException());
        DB::shouldReceive('rollback')->andReturnUndefined();

        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->putJson(self::URI, self::DATA);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testDbConnectionErrorWhileEditingBidStrategy(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new RuntimeException());
        DB::shouldReceive('rollback')->andReturnUndefined();

        /** @var User $user */
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        $bidStrategy = BidStrategy::register('test', $user->id);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->patchJson(self::URI.'/'.$bidStrategyPublicId, self::DATA);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @dataProvider invalidBidStrategyDataProvider
     *
     * @param array $data
     */
    public function testAddingBidStrategyInvalid(array $data): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->putJson(self::URI, $data);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function invalidBidStrategyDataProvider(): array
    {
        return [
            'empty-name' => [
                [
                    'name' => '',
                    'details' => [
                        [
                            'category' => 'user:country:us',
                            'rank' => 0.1,
                        ],
                    ],
                ],
            ],
            'rank-too-low' => [
                [
                    'name' => 'test-name',
                    'details' => [
                        [
                            'category' => 'user:country:us',
                            'rank' => -0.1,
                        ],
                    ],
                ],
            ],
            'rank-too-high' => [
                [
                    'name' => 'test-name',
                    'details' => [
                        [
                            'category' => 'user:country:us',
                            'rank' => 2,
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testGetDefaultUuid(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->getJson(self::URI_UUID_GET);

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(['uuid']);
    }

    public function testPutDefaultUuidValid(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create(['is_admin' => 1]);
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register('test', BidStrategy::ADMINISTRATOR_ID);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $responsePut = $this->put(self::URI_UUID_PUT, ['uuid' => $bidStrategyPublicId]);
        $responsePut->assertStatus(Response::HTTP_NO_CONTENT);

        $responseGet = $this->getJson(self::URI_UUID_GET);
        $responseGet->assertStatus(Response::HTTP_OK);
        $responseGet->assertJsonStructure(['uuid']);
        self::assertEquals($bidStrategyPublicId, $responseGet->json('uuid'));
    }

    public function testPutDefaultUuidInvalid(): void
    {
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');

        $response = $this->put(self::URI_UUID_PUT, ['uuid' => '1234']);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPutDefaultUuidNotExisting(): void
    {
        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');

        $response = $this->put(self::URI_UUID_PUT, ['uuid' => '00000000000000000000000000000000']);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testPutDefaultUuidForbidden(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create(['is_admin' => 0]);
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register('test', $user->id);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->put(self::URI_UUID_PUT, ['uuid' => $bidStrategyPublicId]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testPutDefaultUuidOtherUser(): void
    {
        /** @var User $userAdmin */
        $userAdmin = factory(User::class)->create(['is_admin' => 1]);
        $this->actingAs($userAdmin, 'api');
        /** @var User $userOther */
        $userOther = factory(User::class)->create(['is_admin' => 0]);
        $bidStrategy = BidStrategy::register('test', $userOther->id);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->put(self::URI_UUID_PUT, ['uuid' => $bidStrategyPublicId]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testPutDefaultUuidDbConnectionError(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new RuntimeException());
        DB::shouldReceive('rollback')->andReturnUndefined();

        /** @var User $user */
        $user = factory(User::class)->create(['is_admin' => 1]);
        $this->actingAs($user, 'api');
        $bidStrategy = BidStrategy::register('test', BidStrategy::ADMINISTRATOR_ID);
        $bidStrategyPublicId = $bidStrategy->uuid;

        $response = $this->put(self::URI_UUID_PUT, ['uuid' => $bidStrategyPublicId]);
        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
