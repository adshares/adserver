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

use Adshares\Adserver\Mail\AuthRecovery;
use Adshares\Adserver\Mail\UserEmailActivate;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\ServerEventLog;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\ViewModel\Role;
use Adshares\Adserver\ViewModel\ServerEventType;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Domain\ValueObject\HostStatus;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

// phpcs:ignoreFile PHPCompatibility.Miscellaneous.ValidIntegers.HexNumericStringFound
final class ServerMonitoringControllerTest extends TestCase
{
    private const BASE_URI = '/api/v2';
    private const EVENTS_STRUCTURE = [
        'data' => [
            '*' => [
                'createdAt',
                'properties',
                'type',
            ],
        ],
    ];
    private const HOSTS_STRUCTURE = [
        'data' => [
            '*' => [
                'id',
                'status',
                'name',
                'url',
                'walletAddress',
                'lastBroadcast',
                'lastSynchronization',
                'campaignCount',
                'siteCount',
                'connectionErrorCount',
                'infoJson',
                'error',
            ],
        ]
    ];
    private const USER_DATA_STRUCTURE = [
        'id',
        'email',
        'adminConfirmed',
        'emailConfirmed',
        'adsharesWallet' => [
            'walletBalance',
            'bonusBalance',
        ],
        'connectedWallet' => [
            'address',
            'network',
        ],
        'roles',
        'campaignCount',
        'siteCount',
        'lastActiveAt',
        'isBanned',
        'banReason',
    ];
    private const USER_STRUCTURE = [
        'data' => self::USER_DATA_STRUCTURE,
    ];
    private const USERS_STRUCTURE = [
        'data' => [
            '*' => self::USER_DATA_STRUCTURE,
        ],
    ];

    public function testAccessAdminNoJwt(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');

        $response = $this->getJson(self::buildUriForKey('hosts'));
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function testAccessUserNoJwt(): void
    {
        $this->actingAs(User::factory()->create(), 'api');

        $response = $this->getJson(self::buildUriForKey('hosts'));
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function testAccessUserJwt(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson(
            self::buildUriForKey('hosts'),
            $this->getHeaders($user)
        );

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testFetchHosts(): void
    {
        NetworkHost::factory()->create([
            'address' => '0001-00000001-8B4E',
            'status' => HostStatus::Initialization,
            'last_synchronization' => null,
        ]);
        $carbon = (new Carbon())->subMinutes(10);
        NetworkHost::factory()->create([
            'address' => '0001-00000002-BB2D',
            'status' => HostStatus::Operational,
            'last_synchronization' => $carbon,
        ]);

        $response = $this->getResponseForKey('hosts');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::HOSTS_STRUCTURE);
        $response->assertJsonFragment([
            'walletAddress' => '0001-00000001-8B4E',
            'status' => HostStatus::Initialization,
            'lastSynchronization' => null,
        ]);
        $response->assertJsonFragment([
            'walletAddress' => '0001-00000002-BB2D',
            'status' => HostStatus::Operational,
            'lastSynchronization' => $carbon->format(DateTimeInterface::ATOM),
        ]);
    }

    public function testFetchHostsValidateLimit(): void
    {
        $response = $this->getJson(
            self::buildUriForKey('hosts') . '?limit=no',
            self::getHeaders()
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchWallet(): void
    {
        UserLedgerEntry::factory()->create([
            'amount' => 2000,
            'status' => UserLedgerEntry::STATUS_ACCEPTED,
            'type' => UserLedgerEntry::TYPE_DEPOSIT,
        ]);
        UserLedgerEntry::factory()->create([
            'amount' => -2,
            'status' => UserLedgerEntry::STATUS_ACCEPTED,
            'type' => UserLedgerEntry::TYPE_AD_EXPENSE,
        ]);
        UserLedgerEntry::factory()->create([
            'amount' => 500,
            'status' => UserLedgerEntry::STATUS_ACCEPTED,
            'type' => UserLedgerEntry::TYPE_BONUS_INCOME,
        ]);
        UserLedgerEntry::factory()->create([
            'amount' => -30,
            'status' => UserLedgerEntry::STATUS_ACCEPTED,
            'type' => UserLedgerEntry::TYPE_BONUS_EXPENSE,
        ]);

        $response = $this->getResponseForKey('wallet');

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(
            [
                'wallet' => [
                    'balance' => 2468,
                    'unusedBonuses' => 470,
                ]
            ]
        );
    }

    public function testHostConnectionErrorCounterReset(): void
    {
        /** @var NetworkHost $host */
        $host = NetworkHost::factory()->create([
            'status' => HostStatus::Unreachable,
            'failed_connection' => 10,
        ]);

        $response = $this->patchJson(
            self::buildUriForResetHostConnectionErrorCounter($host->id),
            [],
            self::getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK);
        self::assertDatabaseHas(NetworkHost::class, ['failed_connection' => 0]);
    }

    public function testHostConnectionErrorCounterFailWhenHostDoesNotExist(): void
    {
        $nonExistingHostId = 1;

        $response = $this->patchJson(
            self::buildUriForResetHostConnectionErrorCounter($nonExistingHostId),
            [],
            self::getHeaders()
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchEvents(): void
    {
        self::seedServerEvents();

        $response = $this->getJson(self::buildUriForKey('events'), self::getHeaders());
        $response->assertJsonStructure(self::EVENTS_STRUCTURE);
        $json = $response->json('data');
        self::assertEquals(2, count($json));

        $response = $this->getJson(self::buildUriForKey('events', ['limit' => 1]), self::getHeaders());
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::EVENTS_STRUCTURE);
        self::assertEquals(1, count($response->json('data')));
        $response->assertJsonFragment(['type' => ServerEventType::InventorySynchronized]);
    }

    public function testFetchEventsEmpty(): void
    {
        $response = $this->getJson(self::buildUriForKey('events'), self::getHeaders());
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::EVENTS_STRUCTURE);
        $json = $response->json('data');
        self::assertEquals(0, count($json));
    }

    public function testFetchEventsPagination(): void
    {
        self::seedServerEvents();
        self::seedServerEvents();

        $response = $this->getJson(self::buildUriForKey('events', ['limit' => 3]), self::getHeaders());
        $response->assertJsonStructure(self::EVENTS_STRUCTURE);
        self::assertEquals(3, count($response->json('data')));
        self::assertNull($response->json('prevPageUrl'));
        self::assertNotNull($response->json('nextPageUrl'));

        $url = $response->json('nextPageUrl');
        $response = $this->getJson($url, self::getHeaders());
        $response->assertJsonStructure(self::EVENTS_STRUCTURE);
        self::assertEquals(1, count($response->json('data')));
        self::assertNotNull($response->json('prevPageUrl'));
        self::assertNull($response->json('nextPageUrl'));
    }

    public function testFetchEventsByType(): void
    {
        self::seedServerEvents();

        $response = $this->getJson(
            self::buildUriForKey('events', ['filter' => ['type' => ServerEventType::HostBroadcastProcessed->value]]),
            self::getHeaders()
        );
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::EVENTS_STRUCTURE);
        self::assertEquals(1, count($response->json('data')));
        $response->assertJsonFragment(['type' => ServerEventType::HostBroadcastProcessed]);
    }

    public function testFetchEventsByDate(): void
    {
        ServerEventLog::factory()->create([
            'created_at' => new DateTimeImmutable('-1 month'),
            'type' => ServerEventType::InventorySynchronized,
            'properties' => ['test' => 1],
        ]);
        ServerEventLog::factory()->create([
            'created_at' => new DateTimeImmutable(),
            'type' => ServerEventType::InventorySynchronized,
            'properties' => ['test' => 2],
        ]);
        ServerEventLog::factory()->create([
            'created_at' => new DateTimeImmutable('+1 month'),
            'type' => ServerEventType::InventorySynchronized,
            'properties' => ['test' => 3],
        ]);

        $from = (new DateTimeImmutable('-1 day'))->format(DateTimeInterface::ATOM);
        $to = (new DateTimeImmutable('+1 day'))->format(DateTimeInterface::ATOM);
        $response = $this->getJson(
            self::buildUriForKey('events', [
                'filter' => [
                    'createdAt' => [
                        'from' => $from,
                        'to' => $to,
                    ]
                ]
            ]),
            self::getHeaders()
        );
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::EVENTS_STRUCTURE);
        self::assertEquals(1, count($response->json('data')));
        $response->assertJsonFragment(['type' => ServerEventType::InventorySynchronized]);
        $response->assertJsonFragment(['test' => 2]);
    }

    public function testFetchEventsValidationLimit(): void
    {
        $response = $this->getJson(
            self::buildUriForKey('events', ['limit' => 'no']),
            self::getHeaders()
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchEventsValidationTypeInvalid(): void
    {
        $response = $this->getJson(
            self::buildUriForKey('events', ['filter' => ['type' => 'invalid']]),
            self::getHeaders()
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @dataProvider invalidDateProvider
     */
    public function testFetchEventsValidationFrom(string $from): void
    {
        $response = $this->getJson(
            self::buildUriForKey('events', [
                'filter' => [
                    'createdAt' => [
                        'from' => $from,
                    ]
                ]
            ]),
            self::getHeaders()
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @dataProvider invalidDateProvider
     */
    public function testFetchEventsValidationTo(string $to): void
    {
        $response = $this->getJson(
            self::buildUriForKey('events', [
                'filter' => [
                    'createdAt' => [
                        'to' => $to,
                    ]
                ]
            ]),
            self::getHeaders()
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function invalidDateProvider(): array
    {
        return [
            'empty' => [''],
            'text' => ['now'],
            'number' => ['2022'],
        ];
    }

    public function testFetchEventsValidationFromArray(): void
    {
        $response = $this->getJson(
            self::buildUriForKey('events', [
                'filter' => [
                    'createdAt' => [
                        'from' => ['2022-10-12T02:00:00+00:00'],
                    ]
                ]
            ]),
            self::getHeaders()
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchEventsValidationToArray(): void
    {
        $response = $this->getJson(
            self::buildUriForKey('events', [
                'filter' => [
                    'createdAt' => [
                        'to' => ['2022-10-12T02:00:00+00:00'],
                    ]
                ]
            ]),
            self::getHeaders()
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchEventValidationDateRange(): void
    {
        $from = (new DateTimeImmutable('+1 day'))->format(DateTimeInterface::ATOM);
        $to = (new DateTimeImmutable('-1 day'))->format(DateTimeInterface::ATOM);
        $response = $this->getJson(
            self::buildUriForKey('events', [
                'filter' => [
                    'createdAt' => [
                        'from' => $from,
                        'to' => $to,
                    ]
                ]
            ]),
            self::getHeaders()
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchLatestEventsByType(): void
    {
        ServerEventLog::factory()->create([
            'created_at' => new DateTimeImmutable('-8 minutes'),
            'type' => ServerEventType::InventorySynchronized,
            'properties' => ['test' => 1],
        ]);
        ServerEventLog::factory()->create([
            'created_at' => new DateTimeImmutable('-6 minutes'),
            'type' => ServerEventType::HostBroadcastProcessed,
            'properties' => ['test' => 2],
        ]);
        ServerEventLog::factory()->create([
            'created_at' => new DateTimeImmutable('-4 minutes'),
            'type' => ServerEventType::InventorySynchronized,
            'properties' => ['test' => 3],
        ]);
        ServerEventLog::factory()->create([
            'created_at' => new DateTimeImmutable('-2 minutes'),
            'type' => ServerEventType::BroadcastSent,
            'properties' => ['test' => 4],
        ]);

        $response = $this->getJson(
            self::buildUriForKey(
                'events/latest',
                [
                    'filter' => [
                        'type' => [
                            ServerEventType::HostBroadcastProcessed->value,
                            ServerEventType::InventorySynchronized->value
                        ]
                    ]
                ]
            ),
            self::getHeaders()
        );
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::EVENTS_STRUCTURE);
        self::assertEquals(2, count($response->json('data')));

        $response->assertJsonFragment([
            'type' => ServerEventType::HostBroadcastProcessed,
            'properties' => ['test' => 2],
        ]);
        $response->assertJsonFragment([
            'type' => ServerEventType::HostBroadcastProcessed,
            'properties' => ['test' => 3],
        ]);
    }

    public function testFetchUsers(): void
    {
        self::seedUsers();
        $admin = User::where('is_admin', true)->first();

        $response = $this->getJson(
            self::buildUriForKey('users'),
            self::getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        self::assertEquals(3, count($response->json('data')));
        $data = $response->json('data');
        $adminData = $data[0];
        self::assertEquals('admin@example.com', $adminData['email']);
        self::assertEquals(0, $adminData['adsharesWallet']['walletBalance']);
        self::assertEquals(0, $adminData['adsharesWallet']['bonusBalance']);
        self::assertNull($adminData['connectedWallet']['address']);
        self::assertNull($adminData['connectedWallet']['network']);
        self::assertEquals(0, $adminData['campaignCount']);
        self::assertEquals(0, $adminData['siteCount']);
        self::assertContains(Role::Admin->value, $adminData['roles']);
        self::assertFalse($adminData['isBanned']);
        self::assertNull($adminData['banReason']);
        $user1 = $data[1];
        self::assertEquals('user1@example.com', $user1['email']);
        self::assertEquals(1e5, $user1['adsharesWallet']['walletBalance']);
        self::assertEquals(2e9, $user1['adsharesWallet']['bonusBalance']);
        self::assertNull($user1['connectedWallet']['address']);
        self::assertNull($user1['connectedWallet']['network']);
        self::assertEquals(1, $user1['campaignCount']);
        self::assertEquals(1, $user1['siteCount']);
        self::assertNotContains(Role::Admin->value, $user1['roles']);
        self::assertContains(Role::Advertiser->value, $user1['roles']);
        self::assertContains(Role::Publisher->value, $user1['roles']);
        self::assertFalse($adminData['isBanned']);
        self::assertNull($adminData['banReason']);
        $user2 = $data[2];
        self::assertEquals('user2@example.com', $user2['email']);
        self::assertEquals(3e11, $user2['adsharesWallet']['walletBalance']);
        self::assertEquals(0, $user2['adsharesWallet']['bonusBalance']);
        self::assertEquals('0xace8d624e8c12c0a16df4a61dee85b0fd3f94ceb', $user2['connectedWallet']['address']);
        self::assertEquals(WalletAddress::NETWORK_BSC, $user2['connectedWallet']['network']);
        self::assertEquals(0, $user2['campaignCount']);
        self::assertEquals(2, $user2['siteCount']);
        self::assertNotContains(Role::Admin->value, $user2['roles']);
        self::assertNotContains(Role::Advertiser->value, $user2['roles']);
        self::assertContains(Role::Publisher->value, $user2['roles']);
        self::assertFalse($adminData['isBanned']);
        self::assertNull($adminData['banReason']);
    }

    public function testFetchUserPaginationWithFilteringAndSorting(): void
    {
        self::seedUsers();
        $admin = User::where('is_admin', true)->first();

        User::factory()
            ->count(10)
            ->sequence(fn($sequence) => ['email' => sprintf('user%d@example.com', $sequence->index + 3)])
            ->create([
                'email_confirmed_at' => new DateTimeImmutable('-10 minutes'),
            ]);

        $response = $this->getJson(
            self::buildUriForKey(
                'users',
                [
                    'limit' => 4,
                    'filter' => ['role' => Role::Advertiser->value, 'query' => 'user'],
                    'orderBy' => 'email:desc'
                ]
            ),
            self::getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        self::assertEquals(4, count($response->json('data')));
        self::assertNull($response->json('prevPageUrl'));
        self::assertNotNull($response->json('nextPageUrl'));
        self::assertEquals(11, $response->json('total'));
        $emails = array_map(fn($entry) => $entry['email'], $response->json(['data']));
        self::assertEquals(['user9@example.com', 'user8@example.com', 'user7@example.com', 'user6@example.com'],
            $emails);

        $url = $response->json('nextPageUrl');
        $response = $this->getJson($url, self::getHeaders());
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        self::assertEquals(4, count($response->json('data')));
        self::assertNotNull($response->json('prevPageUrl'));
        self::assertNotNull($response->json('nextPageUrl'));
        $emails = array_map(fn($entry) => $entry['email'], $response->json(['data']));
        self::assertEquals(['user5@example.com', 'user4@example.com', 'user3@example.com', 'user12@example.com'],
            $emails);

        $url = $response->json('nextPageUrl');
        $response = $this->getJson($url, self::getHeaders());
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        self::assertEquals(3, count($response->json('data')));
        self::assertNotNull($response->json('prevPageUrl'));
        self::assertNull($response->json('nextPageUrl'));
        $emails = array_map(fn($entry) => $entry['email'], $response->json(['data']));
        self::assertEquals(['user11@example.com', 'user10@example.com', 'user1@example.com'], $emails);
    }

    public function testFetchUsersLimit(): void
    {
        self::seedUsers();
        $admin = User::where('is_admin', true)->first();

        $response = $this->getJson(
            self::buildUriForKey('users', ['limit' => 1]),
            self::getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        self::assertEquals(1, count($response->json('data')));
        $response->assertJsonPath('data.0.email', 'admin@example.com');
    }

    public function testFetchUsersOrderByInvalidCategory(): void
    {
        self::seedUsers();
        $admin = User::where('is_admin', true)->first();

        $response = $this->getJson(
            self::buildUriForKey('users', ['orderBy' => 'id']),
            self::getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchUsersOrderByInvalidDirection(): void
    {
        self::seedUsers();
        $admin = User::where('is_admin', true)->first();

        $response = $this->getJson(
            self::buildUriForKey('users', ['orderBy' => 'email:up']),
            self::getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @dataProvider fetchUsersOrderByProvider
     */
    public function testFetchUsersOrderBy(string $orderBy, string $expectedEmailOfFirst): void
    {
        self::seedUsers();
        $admin = User::where('is_admin', true)->first();

        $response = $this->getJson(
            self::buildUriForKey('users', ['orderBy' => $orderBy . ':desc']),
            self::getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        $response->assertJsonPath('data.0.email', $expectedEmailOfFirst);
    }

    public function fetchUsersOrderByProvider(): array
    {
        return [
            'bonusBalance' => ['bonusBalance', 'user1@example.com'],
            'campaignCount' => ['campaignCount', 'user1@example.com'],
            'connectedWallet' => ['connectedWallet', 'user2@example.com'],
            'email' => ['email', 'user2@example.com'],
            'lastActiveAt' => ['lastActiveAt', 'admin@example.com'],
            'siteCount' => ['siteCount', 'user2@example.com'],
            'walletBalance' => ['walletBalance', 'user2@example.com'],
            'bonusBalance & campaignCount' => ['bonusBalance:desc,campaignCount', 'user1@example.com'],
        ];
    }

    public function testFetchUsersOrderByArray(): void
    {
        self::seedUsers();
        $admin = User::where('is_admin', true)->first();

        $response = $this->getJson(
            self::buildUriForKey('users', ['orderBy' => ['test1', 'test2']]),
            self::getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @dataProvider fetchUsersFilterByProvider
     */
    public function testFetchUsersFilterBy(array $filter, int $filteredCount, ?string $expectedEmailOfFirst): void
    {
        self::seedUsers();
        $admin = User::where('is_admin', true)->first();

        $response = $this->getJson(
            self::buildUriForKey('users', ['filter' => $filter]),
            self::getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        self::assertEquals($filteredCount, count($response->json('data')));
        if ($filteredCount > 0) {
            $response->assertJsonPath('data.0.email', $expectedEmailOfFirst);
        }
    }

    public function fetchUsersFilterByProvider(): array
    {
        return [
            'admin confirmed' => [['adminConfirmed' => true], 1, 'admin@example.com'],
            'admin not confirmed' => [['adminConfirmed' => false], 2, 'user1@example.com'],
            'email confirmed' => [['emailConfirmed' => true], 2, 'admin@example.com'],
            'email not confirmed' => [['emailConfirmed' => false], 1, 'user2@example.com'],
            Role::Admin->value => [['role' => Role::Admin->value], 1, 'admin@example.com'],
            Role::Advertiser->value => [['role' => Role::Advertiser->value], 2, 'admin@example.com'],
            Role::Agency->value => [['role' => Role::Agency->value], 0, null],
            Role::Moderator->value => [['role' => Role::Moderator->value], 0, null],
            Role::Publisher->value => [['role' => Role::Publisher->value], 3, 'admin@example.com'],
        ];
    }

    public function testFetchUsersCursorDoesNotChangeWhileSetDoesNotChange(): void
    {
        self::seedUsers();
        $admin = User::where('is_admin', true)->first();

        $responseNoFilter = $this->getJson(
            self::buildUriForKey('users'),
            self::getHeaders($admin)
        );
        $response = $this->getJson(
            self::buildUriForKey('users', ['filter' => ['role' => Role::Advertiser->value]]),
            self::getHeaders($admin)
        );

        $responseNoFilter->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        self::assertEquals($responseNoFilter->json('cursor'), $response->json('cursor'));
    }

    public function testFetchUsersFilterByMultipleRoles(): void
    {
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
        ]);
        User::factory()->create([
            'email' => 'advertiser@example.com',
            'is_publisher' => 0,
        ]);
        User::factory()->create([
            'email' => 'publisher@example.com',
            'is_advertiser' => 0,
            'wallet_address' => new WalletAddress(
                WalletAddress::NETWORK_BSC,
                '0xace8d624e8c12c0a16df4a61dee85b0fd3f94ceb'
            ),
        ]);
        $admin = User::where('is_admin', true)->first();
        $query = ['filter' => ['role' => [Role::Advertiser->value, Role::Publisher->value]]];

        $response = $this->getJson(
            self::buildUriForKey('users', $query),
            self::getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        self::assertEquals(3, count($response->json('data')));
    }

    /**
     * @dataProvider fetchUsersFilterByInvalidProvider
     */
    public function testFetchUsersFilterByInvalid(mixed $filter): void
    {
        self::seedUsers();
        $admin = User::where('is_admin', true)->first();

        $response = $this->getJson(
            self::buildUriForKey('users', ['filter' => $filter]),
            self::getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function fetchUsersFilterByInvalidProvider(): array
    {
        return [
            'array of array' => [['role' => [['advertiser']]]],
            'category' => [['id' => 1]],
            'empty string' => [['role' => '']],
            'invalid bool' => [['adminConfirmed' => 'invalid']],
            'role' => [['role' => 'user']],
            'string' => ['role'],
        ];
    }

    public function testFetchUsersQueryByEmail(): void
    {
        self::seedUsers();
        $admin = User::where('is_admin', true)->first();

        $response = $this->getJson(
            self::buildUriForKey('users', ['filter' => ['query' => 'user1']]),
            self::getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        self::assertEquals(1, count($response->json('data')));
        $response->assertJsonPath('data.0.email', 'user1@example.com');
    }

    public function testFetchUsersQueryByWalletAddress(): void
    {
        self::seedUsers();
        $admin = User::where('is_admin', true)->first();

        $response = $this->getJson(
            self::buildUriForKey('users', ['filter' => ['query' => 'ace8d62']]),
            self::getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        self::assertEquals(1, count($response->json('data')));
        $response->assertJsonPath('data.0.connectedWallet', [
            'address' => '0xace8d624e8c12c0a16df4a61dee85b0fd3f94ceb',
            'network' => WalletAddress::NETWORK_BSC,
        ]);
    }

    public function testFetchUsersQueryByCampaignLandingUrlWalletAddress(): void
    {
        self::seedUsers();
        $admin = User::where('is_admin', true)->first();

        $response = $this->getJson(
            self::buildUriForKey('users', ['filter' => ['query' => 'ads']]),
            self::getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        self::assertEquals(1, count($response->json('data')));
        $response->assertJsonPath('data.0.email', 'user1@example.com');
    }

    public function testFetchUsersQueryBySiteDomain(): void
    {
        self::seedUsers();
        $admin = User::where('is_admin', true)->first();

        $response = $this->getJson(
            self::buildUriForKey('users', ['filter' => ['query' => 'test']]),
            self::getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        self::assertEquals(2, count($response->json('data')));
    }

    public function testFetchUsersQueryByArray(): void
    {
        self::seedUsers();
        $admin = User::where('is_admin', true)->first();

        $response = $this->getJson(
            self::buildUriForKey('users', ['filter' => ['query' => ['test', 'ads']]]),
            self::getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        self::assertEquals(1, count($response->json('data')));
        $response->assertJsonPath('data.0.email', 'user1@example.com');
    }

    public function testPatchUserBan(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['is_banned' => 0, 'ban_reason' => null]);

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'ban'),
            ['reason' => 'suspicious activity'],
            self::getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        $user = $user->refresh();
        self::assertTrue($user->isBanned());
        self::assertEquals('suspicious activity', $user->ban_reason);
    }

    public function testPatchUserConfirm(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['admin_confirmed_at' => null]);

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'confirm'),
            [],
            self::getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertNotNull($user->refresh()->admin_confirmed_at);
    }

    public function testPatchUserDelete(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->delete(
            sprintf('%s/%d', self::buildUriForKey('users'), $user->id),
            [],
            self::getHeaders()
        );

        $response->assertStatus(Response::HTTP_NO_CONTENT);
        self::assertNotNull($user->refresh()->deleted_at);
    }

    public function testPatchUserDenyAdvertising(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['is_advertiser' => 1]);

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'denyAdvertising'),
            [],
            self::getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertFalse($user->refresh()->isAdvertiser());
    }

    public function testPatchUserDenyPublishing(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['is_publisher' => 1]);

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'denyPublishing'),
            [],
            self::getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertFalse($user->refresh()->isPublisher());
    }

    public function testPatchUserGrantAdvertising(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['is_advertiser' => 0]);

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'grantAdvertising'),
            [],
            self::getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertTrue($user->refresh()->isAdvertiser());
    }

    public function testPatchUserGrantPublishing(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['is_publisher' => 0]);

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'grantPublishing'),
            [],
            self::getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertTrue($user->refresh()->isPublisher());
    }

    public function testPatchUserSwitchUserToAgency(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['is_agency' => 0]);

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'switchToAgency'),
            [],
            self::getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertTrue($user->refresh()->isAgency());
    }

    public function testPatchUserSwitchUserToAgencyByRegularUser(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['is_agency' => 0]);

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'switchToAgency'),
            [],
            self::getHeaders($user)
        );

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        self::assertFalse($user->refresh()->isAgency());
    }

    public function testPatchUserSwitchUserToAgencyWhileUserIsAgency(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['is_agency' => 1]);

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'switchToAgency'),
            [],
            self::getHeaders()
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertTrue($user->refresh()->isAgency());
    }

    public function testPatchUserSwitchUserToAgencyWhileUserDeleted(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'deleted_at' => new DateTimeImmutable('-1 minute'),
            'is_agency' => 0,
        ]);

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'switchToAgency'),
            [],
            self::getHeaders()
        );

        $response->assertStatus(Response::HTTP_NOT_FOUND);
        self::assertFalse($user->refresh()->isAgency());
    }

    public function testPatchUserSwitchUserToModerator(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['is_moderator' => 0]);

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'switchToModerator'),
            [],
            self::getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertTrue($user->refresh()->isModerator());
    }

    public function testPatchUserSwitchUserToModeratorByModerator(): void
    {
        $moderator = User::factory()->create(['is_moderator' => 1]);
        /** @var User $user */
        $user = User::factory()->create(['is_moderator' => 0]);

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'switchToModerator'),
            [],
            self::getHeaders($moderator)
        );

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        self::assertFalse($user->refresh()->isModerator());
    }

    public function testPatchUserSwitchUserToModeratorWhileUserIsModerator(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['is_moderator' => 1]);

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'switchToModerator'),
            [],
            self::getHeaders()
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertTrue($user->refresh()->isModerator());
    }

    public function testPatchUserSwitchUserToModeratorWhileUserDeleted(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'deleted_at' => new DateTimeImmutable('-1 minute'),
            'is_moderator' => 0,
        ]);

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'switchToModerator'),
            [],
            self::getHeaders()
        );

        $response->assertStatus(Response::HTTP_NOT_FOUND);
        self::assertFalse($user->refresh()->isModerator());
    }

    public function testPatchUserSwitchUserToRegular(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['is_moderator' => 1]);

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'switchToRegular'),
            [],
            self::getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertFalse($user->refresh()->isModerator());
    }

    public function testPatchUserSwitchUserToRegularByRegularUser(): void
    {
        $user = User::factory()->create();
        /** @var User $moderator */
        $moderator = User::factory()->create(['is_moderator' => 1]);

        $response = $this->patchJson(
            self::buildUriForPatchUser($moderator->id, 'switchToRegular'),
            [],
            self::getHeaders($user)
        );

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        self::assertTrue($moderator->refresh()->isModerator());
    }

    public function testPatchUserSwitchUserToRegularByModeratorWhileUserIsModerator(): void
    {
        $moderator = User::factory()->create(['is_moderator' => 1]);
        /** @var User $user */
        $user = User::factory()->create(['is_moderator' => 1]);

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'switchToRegular'),
            [],
            self::getHeaders($moderator)
        );

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        self::assertTrue($user->refresh()->isModerator());
    }

    public function testPatchUserSwitchUserToRegularWhileUserDeleted(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'deleted_at' => new DateTimeImmutable('-1 minute'),
            'is_moderator' => 1,
        ]);

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'switchToRegular'),
            [],
            self::getHeaders()
        );

        $response->assertStatus(Response::HTTP_NOT_FOUND);
        self::assertTrue($user->refresh()->isModerator());
    }

    public function testPatchUserUnban(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['is_banned' => 1, 'ban_reason' => 'suspicious activity']);

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'unban'),
            ['reason' => 'suspicious activity'],
            self::getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        $user = $user->refresh();
        self::assertFalse($user->isBanned());
    }

    public function testPatchUserInvalidAction(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'invalid'),
            [],
            self::getHeaders()
        );
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    private function getResponseForKey(string $key): TestResponse
    {
        return $this->getJson(
            self::buildUriForKey($key),
            self::getHeaders()
        );
    }

    private static function getHeaders($user = null): array
    {
        if (null === $user) {
            $user = User::factory()->admin()->create();
        }
        return ['Authorization' => 'Bearer ' . JWTAuth::fromUser($user)];
    }

    private static function buildUriForKey(string $key, array $query = null): string
    {
        $uri = sprintf('%s/%s', self::BASE_URI, $key);
        if (null !== $query) {
            $uri .= '?' . http_build_query($query);
        }
        return $uri;
    }

    private static function buildUriForResetHostConnectionErrorCounter(int $hostId): string
    {
        return sprintf('/%s/%d/reset', self::buildUriForKey('hosts'), $hostId);
    }

    private static function buildUriForPatchUser(int $userId, string $action = null): string
    {
        $uri = sprintf('%s/%d', self::buildUriForKey('users'), $userId);
        if (null !== $action) {
            $uri = sprintf('%s/%s', $uri, $action);
        }
        return $uri;
    }

    private static function seedServerEvents(): void
    {
        ServerEventLog::factory()->create(['type' => ServerEventType::HostBroadcastProcessed]);
        ServerEventLog::factory()->create(['type' => ServerEventType::InventorySynchronized]);
    }

    private static function seedUsers(): void
    {
        /** @var User $admin */
        User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'updated_at' => new DateTimeImmutable('+10 minutes'),
        ]);

        /** @var User $user1 */
        $user1 = User::factory()->create([
            'email' => 'user1@example.com',
            'email_confirmed_at' => new DateTimeImmutable('-10 minutes'),
        ]);
        Campaign::factory()->create([
            'user_id' => $user1->id,
            'status' => Campaign::STATUS_ACTIVE,
            'landing_url' => 'https://ads.example.com'
        ]);
        Site::factory()->create([
            'user_id' => $user1->id,
            'domain' => 'my-test.com',
        ]);
        UserLedgerEntry::factory()->create([
            'user_id' => $user1->id,
            'type' => UserLedgerEntry::TYPE_AD_INCOME,
            'amount' => 1e5,
        ]);
        UserLedgerEntry::factory()->create([
            'user_id' => $user1->id,
            'type' => UserLedgerEntry::TYPE_BONUS_INCOME,
            'amount' => 2e9,
        ]);

        /** @var User $user2 */
        $user2 = User::factory()->create([
            'email' => 'user2@example.com',
            'is_advertiser' => 0,
            'wallet_address' => new WalletAddress(
                WalletAddress::NETWORK_BSC,
                '0xace8d624e8c12c0a16df4a61dee85b0fd3f94ceb'
            ),
        ]);
        Site::factory()->count(2)->create([
            'user_id' => $user2->id,
            'domain' => 'test-domain.com',
        ]);
        UserLedgerEntry::factory()->create([
            'user_id' => $user2->id,
            'type' => UserLedgerEntry::TYPE_AD_INCOME,
            'amount' => 3e11,
        ]);
    }

    public function testAddUser(): void
    {
        $response = $this->postJson(self::buildUriForKey('users'), self::getUserData(), self::getHeaders());

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE)
            ->assertJsonMissingPath('data.password');
        self::assertDatabaseHas(User::class, [
            'email' => 'user@example.com',
            'wallet_address' => (new WalletAddress(WalletAddress::NETWORK_ADS, '0001-00000001-8B4E'))->toString(),
            'is_advertiser' => 1,
            'is_publisher' => 1,
        ]);
        $user = User::fetchByEmail('user@example.com');
        self::assertNotNull($user->admin_confirmed_at);
        self::assertNull($user->email_confirmed_at);
        Mail::assertQueued(AuthRecovery::class);
    }

    public function testAddUserRequireEmailVerification(): void
    {
        Config::updateAdminSettings([Config::EMAIL_VERIFICATION_REQUIRED => '1']);
        $response = $this->postJson(
            self::buildUriForKey('users'),
            self::getUserData([], 'forcePasswordChange'),
            self::getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE)
            ->assertJsonStructure(['data' => ['password']]);
        self::assertDatabaseHas(User::class, [
            'email' => 'user@example.com',
            'wallet_address' => (new WalletAddress(WalletAddress::NETWORK_ADS, '0001-00000001-8B4E'))->toString(),
            'is_advertiser' => 1,
            'is_publisher' => 1,
        ]);
        $user = User::fetchByEmail('user@example.com');
        self::assertNotNull($user->admin_confirmed_at);
        self::assertNull($user->email_confirmed_at);
        Mail::assertQueued(UserEmailActivate::class);
    }

    public function testAddUserNoEmailVerification(): void
    {
        Config::updateAdminSettings([Config::EMAIL_VERIFICATION_REQUIRED => '0']);
        $response = $this->postJson(
            self::buildUriForKey('users'),
            self::getUserData([], 'forcePasswordChange'),
            self::getHeaders()
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE)
            ->assertJsonStructure(['data' => ['password']]);
        self::assertDatabaseHas(User::class, [
            'email' => 'user@example.com',
            'wallet_address' => (new WalletAddress(WalletAddress::NETWORK_ADS, '0001-00000001-8B4E'))->toString(),
            'is_advertiser' => 1,
            'is_publisher' => 1,
        ]);
        $user = User::fetchByEmail('user@example.com');
        self::assertNotNull($user->admin_confirmed_at);
        self::assertNotNull($user->email_confirmed_at);
        Mail::assertNothingQueued();
    }

    /**
     * @dataProvider addUserInvalidDataProvider
     */
    public function testAddUserWhileDataInvalid(array $data): void
    {
        User::factory()->create([
            'email' => 'user5@example.com',
            'wallet_address' => new WalletAddress(WalletAddress::NETWORK_ADS, '0001-00000004-DBEB'),
        ]);
        $response = $this->postJson(self::buildUriForKey('users'), $data, self::getHeaders());

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function addUserInvalidDataProvider(): array
    {
        return [
            'duplicated email' => [self::getUserData(['email' => 'user5@example.com'])],
            'duplicated wallet' => [
                self::getUserData([
                    'wallet' => [
                        'address' => '0001-00000004-DBEB',
                        'network' => WalletAddress::NETWORK_ADS,
                    ]
                ])
            ],
            'invalid email' => [self::getUserData(['email' => 'invalid'])],
            'invalid force password change' => [self::getUserData(['forcePasswordChange' => 'user@example.com'])],
            'invalid role' => [self::getUserData(['role' => ['invalid']])],
            'invalid wallet' => [
                self::getUserData([
                    'wallet' => [
                        'address' => 'invalid',
                        'network' => WalletAddress::NETWORK_ADS,
                    ]
                ])
            ],
            'missing email while notify' => [self::getUserData([], 'email')],
            'missing email and wallet' => [['role' => Role::Publisher->value]],
            'missing role' => [self::getUserData([], 'role')],
            'role conflict' => [self::getUserData(['role' => [Role::Agency->value, Role::Moderator->value]])],
        ];
    }

    public function testAddUserWhileDbError(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new RuntimeException('test-exception'));
        DB::shouldReceive('rollback')->andReturnUndefined();

        $response = $this->postJson(self::buildUriForKey('users'), self::getUserData(), self::getHeaders());

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    private static function getUserData(array $mergeData = [], string $remove = null): array
    {
        $data = array_merge([
            'email' => 'user@example.com',
            'role' => [Role::Advertiser->value, Role::Publisher->value],
            'wallet' => [
                'address' => '0001-00000001-8B4E',
                'network' => WalletAddress::NETWORK_ADS,
            ],
            'forcePasswordChange' => true,
        ], $mergeData);

        if (null !== $remove) {
            unset($data[$remove]);
        }

        return $data;
    }

    public function testEditUserEmailRequireEmailVerification(): void
    {
        Config::updateAdminSettings([Config::EMAIL_VERIFICATION_REQUIRED => '1']);
        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'email_confirmed_at' => new DateTimeImmutable('-10 days'),
        ]);
        $data = ['email' => 'user2@example.com'];

        $response = $this->patchJson(self::buildUriForPatchUser($user->id), $data, self::getHeaders());

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertDatabaseHas(User::class, [
            'email' => 'user2@example.com',
        ]);
        $user = User::fetchByEmail('user2@example.com');
        self::assertNotNull($user->admin_confirmed_at);
        self::assertNull($user->email_confirmed_at);
        Mail::assertQueued(UserEmailActivate::class);
    }

    public function testEditUserEmailNoEmailVerification(): void
    {
        Config::updateAdminSettings([Config::EMAIL_VERIFICATION_REQUIRED => '0']);
        /** @var User $user */
        $user = User::factory()->create(['email' => 'user@example.com']);
        $data = ['email' => 'user2@example.com'];

        $response = $this->patchJson(self::buildUriForPatchUser($user->id), $data, self::getHeaders());

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertDatabaseHas(User::class, [
            'email' => 'user2@example.com',
        ]);
        $user = User::fetchByEmail('user2@example.com');
        self::assertNotNull($user->admin_confirmed_at);
        self::assertNotNull($user->email_confirmed_at);
        Mail::assertNothingQueued();
    }

    public function testEditUserWalletAddress(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $data = [
            'wallet' => [
                'address' => '0001-00000001-8B4E',
                'network' => WalletAddress::NETWORK_ADS,
            ],
        ];

        $response = $this->patchJson(self::buildUriForPatchUser($user->id), $data, self::getHeaders());

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertDatabaseHas(User::class, [
            'wallet_address' => (new WalletAddress(WalletAddress::NETWORK_ADS, '0001-00000001-8B4E'))->toString(),
        ]);
    }

    public function testEditUserRole(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $data = [
            'role' => [
                Role::Advertiser->value,
            ],
        ];

        $response = $this->patchJson(self::buildUriForPatchUser($user->id), $data, self::getHeaders());

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertDatabaseHas(User::class, [
            'is_advertiser' => 1,
            'is_publisher' => 0,
        ]);
    }

    public function testEditUserInvalid(): void
    {
        $data = ['email' => 'user2@example.com'];

        $response = $this->patchJson(self::buildUriForPatchUser(PHP_INT_MAX), $data, self::getHeaders());

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /**
     * @dataProvider editUserInvalidDataProvider
     */
    public function testEditUserWhileDataInvalid(array $data): void
    {
        User::factory()->create([
            'email' => 'user2@example.com',
            'wallet_address' => new WalletAddress(WalletAddress::NETWORK_ADS, '0001-00000001-8B4E'),
        ]);
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->patchJson(self::buildUriForPatchUser($user->id), $data, self::getHeaders());

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function editUserInvalidDataProvider(): array
    {
        return [
            'duplicated email' => [['email' => 'user2@example.com']],
            'duplicated wallet' => [
                [
                    'wallet' => [
                        'address' => '0001-00000001-8B4E',
                        'network' => WalletAddress::NETWORK_ADS
                    ]
                ]
            ],
            'invalid email' => [['email' => 'invalid']],
            'invalid role' => [['role' => ['invalid']]],
            'invalid wallet' => [['wallet' => ['address' => 'invalid', 'network' => WalletAddress::NETWORK_ADS]]],
            'roles conflict' => [['role' => [Role::Agency->value, Role::Moderator->value]]],
        ];
    }

    public function testEditUserWhileDbError(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new RuntimeException('test-exception'));
        DB::shouldReceive('rollback')->andReturnUndefined();
        /** @var User $user */
        $user = User::factory()->create();
        $data = ['email' => 'user2@example.com'];

        $response = $this->patchJson(self::buildUriForPatchUser($user->id), $data, self::getHeaders());

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
