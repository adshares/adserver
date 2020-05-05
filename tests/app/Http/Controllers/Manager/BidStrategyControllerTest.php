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

class BidStrategyControllerTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/campaigns/bid-strategy';

    private const STRUCTURE_CHECK = [
        [
            'id',
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
        $bidStrategyId = $bidStrategy->id;

        $response = $this->patchJson(self::URI.'/'.$bidStrategyId, self::DATA);
        $response->assertStatus(Response::HTTP_NO_CONTENT);

        $bidStrategyEdited = BidStrategy::fetchById($bidStrategyId)->toArray();
        self::assertEquals(self::DATA['name'], $bidStrategyEdited['name']);
        self::assertEquals(self::DATA['details'], $bidStrategyEdited['details']);
    }

    public function testEditAlienBidStrategy(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        $bidStrategy = BidStrategy::register('test', $user->id + 1);
        $bidStrategyId = $bidStrategy->id;

        $response = $this->patchJson(self::URI.'/'.$bidStrategyId, self::DATA);
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function testEditNotExistingBidStrategy(): void
    {
        /** @var User $user */
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        $bidStrategyId = 1000;

        $response = $this->patchJson(self::URI.'/'.$bidStrategyId, self::DATA);
        $response->assertStatus(Response::HTTP_NOT_FOUND);
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
}
