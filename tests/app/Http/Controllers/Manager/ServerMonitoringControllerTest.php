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
    private const USERS_STRUCTURE = [
        'data' => [
            '*' => [
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
                'lastLogin',
            ],
        ],
    ];

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
            self::EVENTS_URI . '?types[]=' . ServerEventType::HostBroadcastProcessed->value,
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
            self::EVENTS_URI . '?from=' . urlencode($from) . '&to=' . urlencode($to),
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

    public function testFetchEventsValidationTypeNotArray(): void
    {
        $response = $this->getJson(
            self::EVENTS_URI . '?types=' . ServerEventType::HostBroadcastProcessed->value,
            self::getHeaders()
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchEventsValidationTypeInvalid(): void
    {
        $response = $this->getJson(
            self::EVENTS_URI . '?types[]=invalid',
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
            self::EVENTS_URI . '?from=' . $from,
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
            self::EVENTS_URI . '?to=' . $to,
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
            self::EVENTS_URI . '?from[]=2022-10-12T02%3A00%3A00%2B00%3A00',
            self::getHeaders()
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchEventsValidationToArray(): void
    {
        $response = $this->getJson(
            self::EVENTS_URI . '?to[]=2022-10-12T02%3A00%3A00%2B00%3A00',
            self::getHeaders()
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchEventValidationDateRange(): void
    {
        $from = (new DateTimeImmutable('+1 day'))->format(DateTimeInterface::ATOM);
        $to = (new DateTimeImmutable('-1 day'))->format(DateTimeInterface::ATOM);
        $response = $this->getJson(
            self::EVENTS_URI . '?from=' . urlencode($from) . '&to=' . urlencode($to),
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
            . '?types[]=' . ServerEventType::HostBroadcastProcessed->value
            . '&types[]=' . ServerEventType::InventorySynchronized->value,
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
        self::assertContains(Role::Advertiser->value, $user2['roles']);
        self::assertContains(Role::Publisher->value, $user2['roles']);
    }

    public function testFetchUsersLimit(): void
    {
        self::seedUsers();
        $admin = User::where('is_admin', true)->first();

        $response = $this->getJson(
            self::buildUriForKey('users') . '?limit=1',
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
            self::buildUriForKey('users') . '?orderBy=id',
            self::getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchUsersOrderByArray(): void
    {
        self::seedUsers();
        $admin = User::where('is_admin', true)->first();

        $response = $this->getJson(
            self::buildUriForKey('users') . '?orderBy[]=email',
            self::getHeaders($admin)
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchUsersOrderByInvalidDirection(): void
    {
        self::seedUsers();
        $admin = User::where('is_admin', true)->first();

        $response = $this->getJson(
            self::buildUriForKey('users') . '?orderBy=email&direction=up',
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
            self::buildUriForKey('users') . '?orderBy=' . $orderBy . '&direction=desc',
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
            'lastLogin' => ['lastLogin', 'admin@example.com'],
            'siteCount' => ['siteCount', 'user2@example.com'],
            'walletBalance' => ['walletBalance', 'user2@example.com'],
        ];
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

    private static function buildUriForKey(string $key): string
    {
        return self::MONITORING_URI . '/' . $key;
    }

    private static function buildUriForResetHostConnectionErrorCounter(string $hostId): string
    {
        return sprintf('/api/monitoring/hosts/%d/reset', $hostId);
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
        $user1 = User::factory()->create(['email' => 'user1@example.com']);
        Campaign::factory()->create([
            'user_id' => $user1->id,
            'status' => Campaign::STATUS_ACTIVE,
        ]);
        Site::factory()->create(['user_id' => $user1->id]);
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
            'wallet_address' => new WalletAddress(
                WalletAddress::NETWORK_BSC,
                '0xace8d624e8c12c0a16df4a61dee85b0fd3f94ceb'
            ),
        ]);
        Site::factory()->count(2)->create(['user_id' => $user2->id]);
        UserLedgerEntry::factory()->create([
            'user_id' => $user2->id,
            'type' => UserLedgerEntry::TYPE_AD_INCOME,
            'amount' => 3e11,
        ]);
    }
}
