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

use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\ServerEventLog;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\ViewModel\Role;
use Adshares\Adserver\ViewModel\ServerEventType;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Supply\Domain\ValueObject\HostStatus;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

// phpcs:ignoreFile PHPCompatibility.Miscellaneous.ValidIntegers.HexNumericStringFound
final class ServerMonitoringControllerTest extends TestCase
{
    private const EVENTS_URI = '/api/monitoring/events';
    private const EVENTS_STRUCTURE = [
        'data' => [
            '*' => [
                'createdAt',
                'properties',
                'type',
            ],
        ],
    ];
    private const MONITORING_URI = '/api/monitoring';
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
    ];
    private const USER_STRUCTURE = [
        'data' => self::USER_DATA_STRUCTURE,
    ];
    private const USERS_STRUCTURE = [
        'data' => [
            '*' => self::USER_DATA_STRUCTURE,
        ],
    ];
    private const USERS_URI = '/api/monitoring/users';

    public function testAccessAdminNoJwt(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');

        $response = $this->getJson(self::buildUriForKey('wallet'));
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function testAccessUserNoJwt(): void
    {
        $this->actingAs(User::factory()->create(), 'api');

        $response = $this->getJson(self::buildUriForKey('wallet'));
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function testAccessUserJwt(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson(
            self::buildUriForKey('wallet'),
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

    public function testFetchByInvalidKey(): void
    {
        $response = $this->getResponseForKey('invalid');

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
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

        $response = $this->getJson(self::EVENTS_URI, self::getHeaders());
        $response->assertJsonStructure(self::EVENTS_STRUCTURE);
        $json = $response->json('data');
        self::assertEquals(2, count($json));

        $response = $this->getJson(self::EVENTS_URI . '?limit=1', self::getHeaders());
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::EVENTS_STRUCTURE);
        self::assertEquals(1, count($response->json('data')));
        $response->assertJsonFragment(['type' => ServerEventType::InventorySynchronized]);
    }

    public function testFetchEventsEmpty(): void
    {
        $response = $this->getJson(self::EVENTS_URI, self::getHeaders());
        $response->assertJsonStructure(self::EVENTS_STRUCTURE);
        $json = $response->json('data');
        self::assertEquals(0, count($json));
    }

    public function testFetchEventsPagination(): void
    {
        self::seedServerEvents();
        self::seedServerEvents();

        $response = $this->getJson(self::EVENTS_URI . '?limit=3', self::getHeaders());
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
            self::EVENTS_URI . '?filter[type]=' . ServerEventType::HostBroadcastProcessed->value,
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
            self::EVENTS_URI .
            '?filter[createdAt][from]=' . urlencode($from) .
            '&filter[createdAt][to]=' . urlencode($to),
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
            self::EVENTS_URI . '?limit=no',
            self::getHeaders()
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchEventsValidationTypeInvalid(): void
    {
        $response = $this->getJson(
            self::EVENTS_URI . '?filter[type]=invalid',
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
            self::EVENTS_URI . '?filter[createdAt][from]=' . $from,
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
            self::EVENTS_URI . '?filter[createdAt][to]=' . $to,
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
            self::EVENTS_URI . '?filter[createdAt][from][]=2022-10-12T02%3A00%3A00%2B00%3A00',
            self::getHeaders()
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchEventsValidationToArray(): void
    {
        $response = $this->getJson(
            self::EVENTS_URI . '?filter[createdAt][to][]=2022-10-12T02%3A00%3A00%2B00%3A00',
            self::getHeaders()
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchEventValidationDateRange(): void
    {
        $from = (new DateTimeImmutable('+1 day'))->format(DateTimeInterface::ATOM);
        $to = (new DateTimeImmutable('-1 day'))->format(DateTimeInterface::ATOM);
        $response = $this->getJson(
            self::EVENTS_URI .
            '?filter[createdAt][from]=' . urlencode($from) .
            '&filter[createdAt][to]=' . urlencode($to),
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
            self::buildUriForKey('latest-events')
            . '?filter[type][]=' . ServerEventType::HostBroadcastProcessed->value
            . '&filter[type][]=' . ServerEventType::InventorySynchronized->value,
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
        ];
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
            self::buildUriForKey('users', ['query' => 'user1']),
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
            self::buildUriForKey('users', ['query' => 'ace8d62']),
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
            self::buildUriForKey('users', ['query' => 'ads']),
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
            self::buildUriForKey('users', ['query' => 'test']),
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
            self::buildUriForKey('users', ['query' => ['test1', 'test2']]),
            self::getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
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
        self::assertEquals(1, $user->is_banned);
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
            sprintf('%s/%d', self::USERS_URI, $user->id),
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
        self::assertEquals(0, $user->is_banned);
    }

//    public function patchUserProvider(): array
//    {
//        return [
//            'ban' => ['ban', 'banUser'],
//            'delete' => ['delete', 'deleteUser'],
//            'switchToModerator' => ['switchToModerator', 'switchUserToModerator'],
//            'unban' => ['unban', 'unbanUser'],
//            'switchToAgency' => ['switchToAgency', 'switchUserToAgency'],
//            'switchToRegular' => ['switchToRegular', 'switchUserToRegular'],
//        ];
//    }

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
        $uri = self::MONITORING_URI . '/' . $key;

        if (null !== $query) {
            $uri .= '?' . http_build_query($query);
        }

        return $uri;
    }

    private static function buildUriForResetHostConnectionErrorCounter(int $hostId): string
    {
        return sprintf('/api/monitoring/hosts/%d/reset', $hostId);
    }

    private static function buildUriForPatchUser(int $userId, string $action): string
    {
        return sprintf('%s/%d/%s', self::USERS_URI, $userId, $action);
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
}
