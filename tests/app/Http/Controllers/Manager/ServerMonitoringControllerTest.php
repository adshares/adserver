<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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
use Adshares\Adserver\Mail\UserBanned;
use Adshares\Adserver\Mail\UserConfirmed;
use Adshares\Adserver\Mail\UserEmailActivate;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\BannerClassification;
use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\BidStrategyDetail;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Classification;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\RefLink;
use Adshares\Adserver\Models\ServerEventLog;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Models\TurnoverEntry;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Models\UserSettings;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\ViewModel\Role;
use Adshares\Adserver\ViewModel\ServerEventType;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Domain\ValueObject\HostStatus;
use Adshares\Supply\Domain\ValueObject\TurnoverEntryType;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\Passport;
use PDOException;
use Symfony\Component\HttpFoundation\Response;

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
                'lastSynchronizationAttempt',
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
            'withdrawableBalance',
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
        $this->setUpUser();

        $response = $this->getJson(self::buildUriForKey('hosts'));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testFetchHosts(): void
    {
        $this->setUpAdmin();
        NetworkHost::factory()->create([
            'address' => '0001-00000001-8B4E',
            'status' => HostStatus::Initialization,
            'last_synchronization' => null,
            'last_synchronization_attempt' => null,
        ]);
        $carbon = (new Carbon())->subMinutes(10);
        NetworkHost::factory()->create([
            'address' => '0001-00000002-BB2D',
            'status' => HostStatus::Operational,
            'last_synchronization' => $carbon,
            'last_synchronization_attempt' => $carbon,
        ]);

        $response = $this->getJson(self::buildUriForKey('hosts'));

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure(self::HOSTS_STRUCTURE);
        $response->assertJsonFragment([
            'walletAddress' => '0001-00000001-8B4E',
            'status' => HostStatus::Initialization,
            'lastSynchronization' => null,
            'lastSynchronizationAttempt' => null,
        ]);
        $response->assertJsonFragment([
            'walletAddress' => '0001-00000002-BB2D',
            'status' => HostStatus::Operational,
            'lastSynchronization' => $carbon->format(DateTimeInterface::ATOM),
            'lastSynchronizationAttempt' => $carbon->format(DateTimeInterface::ATOM),
        ]);
    }

    public function testFetchHostsValidateLimit(): void
    {
        $this->setUpAdmin();

        $response = $this->getJson(
            self::buildUriForKey('hosts') . '?limit=no',
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchWallet(): void
    {
        $this->setUpAdmin();
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

        $response = $this->getJson(self::buildUriForKey('wallet'));

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

    public function testFetchTurnover(): void
    {
        $this->setUpAdmin();
        $this->seedTurnoverData();

        $response = $this->getJson(
            self::buildUriForKey(
                'turnover',
                [
                    'filter' => [
                        'date' => [
                            'from' => (new DateTimeImmutable('2023-04-10 23:30:00'))->format(DateTimeInterface::ATOM),
                            'to' => (new DateTimeImmutable('2023-04-11 23:59:59'))->format(DateTimeInterface::ATOM),
                        ]
                    ]
                ]
            )
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(
            [
                'dspAdvertisersExpense' => 200_000_000_000,
                'dspLicenseFee' => 2_000_000_000,
                'dspOperatorFee' => 19_800_000_000,
                'dspCommunityFee' => 1_782_000_000,
                'dspExpense' => 176_418_000_000,
                'sspIncome' => 2_000,
                'sspLicenseFee' => 0,
                'sspOperatorFee' => 500,
                'sspPublishersIncome' => 1_500,
            ]
        );
    }

    public function testFetchTurnoverWithTimezone(): void
    {
        $this->setUpAdmin();
        TurnoverEntry::factory()
            ->count(2)->sequence(
                ['hour_timestamp' => '2023-04-10 23:00:00'],
                ['hour_timestamp' => '2023-04-11 00:00:00'],
            )
            ->create(
                [
                    'amount' => 100_000_000_000,
                    'type' => TurnoverEntryType::DspAdvertisersExpense,
                ]
            );

        $response = $this->getJson(
            self::buildUriForKey(
                'turnover',
                [
                    'filter' => [
                        'date' => [
                            'from' => '2023-04-11T00:00:00+02:00',
                            'to' => '2023-04-11T23:59:59+02:00',
                        ]
                    ]
                ]
            )
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(
            [
                'dspAdvertisersExpense' => 200_000_000_000,
            ]
        );
    }

    public function testFetchTurnoverByType(): void
    {
        $this->setUpAdmin();
        $this->seedTurnoverData();

        $response = $this->getJson(
            self::buildUriForKey(
                'turnover/DspExpense',
                [
                    'filter' => [
                        'date' => [
                            'from' => (new DateTimeImmutable('2023-04-10 23:30:00'))->format(DateTimeInterface::ATOM),
                            'to' => (new DateTimeImmutable('2023-04-11 23:59:59'))->format(DateTimeInterface::ATOM),
                        ]
                    ]
                ]
            )
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(
            [
                [
                    'adsAddress' => '0001-00000002-BB2D',
                    'amount' => 76_018_000_000,
                ],
                [
                    'adsAddress' => '0001-00000003-AB0C',
                    'amount' => 100_400_000_000,
                ],
            ]
        );
    }

    public function testFetchTurnoverByTypeFailWhileInvalidType(): void
    {
        $this->setUpAdmin();
        $this->seedTurnoverData();

        $response = $this->getJson(
            self::buildUriForKey(
                'turnover/Invalid',
                [
                    'filter' => [
                        'date' => [
                            'from' => (new DateTimeImmutable('2023-04-10 23:30:00'))->format(DateTimeInterface::ATOM),
                            'to' => (new DateTimeImmutable('2023-04-11 23:59:59'))->format(DateTimeInterface::ATOM),
                        ]
                    ]
                ]
            )
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchTurnoverChart(): void
    {
        $this->setUpAdmin();
        $this->seedTurnoverData();

        $response = $this->getJson(
            self::buildUriForKey(
                'turnover/chart/day',
                [
                    'filter' => [
                        'date' => [
                            'from' => (new DateTimeImmutable('2023-04-10 23:30:00'))->format(DateTimeInterface::ATOM),
                            'to' => (new DateTimeImmutable('2023-04-11 23:59:59'))->format(DateTimeInterface::ATOM),
                        ]
                    ]
                ]
            )
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJson(
            [
                [
                    'dspAdvertisersExpense' => 0,
                    'dspLicenseFee' => 0,
                    'dspOperatorFee' => 0,
                    'dspCommunityFee' => 0,
                    'dspExpense' => 0,
                    'sspIncome' => 0,
                    'sspLicenseFee' => 0,
                    'sspOperatorFee' => 0,
                    'sspPublishersIncome' => 0,
                    'date' => '2023-04-10T23:30:00+00:00',
                ],
                [
                    'dspAdvertisersExpense' => 200_000_000_000,
                    'dspLicenseFee' => 2_000_000_000,
                    'dspOperatorFee' => 19_800_000_000,
                    'dspCommunityFee' => 1_782_000_000,
                    'dspExpense' => 176_418_000_000,
                    'sspIncome' => 2_000,
                    'sspLicenseFee' => 0,
                    'sspOperatorFee' => 500,
                    'sspPublishersIncome' => 1_500,
                    'date' => '2023-04-11T00:00:00+00:00',
                ],
            ]
        );
    }

    public function testFetchTurnoverChartFailWhileInvalidResolution(): void
    {
        $this->setUpAdmin();

        $response = $this->getJson(
            self::buildUriForKey(
                'turnover/chart/year',
                [
                    'filter' => [
                        'date' => [
                            'from' => (new DateTimeImmutable('2023-04-10 23:30:00'))->format(DateTimeInterface::ATOM),
                            'to' => (new DateTimeImmutable('2023-04-11 23:59:59'))->format(DateTimeInterface::ATOM),
                        ]
                    ]
                ]
            )
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testHostConnectionErrorCounterReset(): void
    {
        $this->setUpAdmin();
        /** @var NetworkHost $host */
        $host = NetworkHost::factory()->create([
            'status' => HostStatus::Unreachable,
            'failed_connection' => 10,
        ]);

        $response = $this->patchJson(
            self::buildUriForResetHostConnectionErrorCounter($host->id),
        );

        $response->assertStatus(Response::HTTP_OK);
        self::assertDatabaseHas(NetworkHost::class, ['failed_connection' => 0]);
    }

    public function testHostConnectionErrorCounterFailWhenHostDoesNotExist(): void
    {
        $this->setUpAdmin();
        $nonExistingHostId = 1;

        $response = $this->patchJson(
            self::buildUriForResetHostConnectionErrorCounter($nonExistingHostId),
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchEvents(): void
    {
        $this->setUpAdmin();
        self::seedServerEvents();

        $response = $this->getJson(self::buildUriForKey('events'));
        $response->assertJsonStructure(self::EVENTS_STRUCTURE);
        $json = $response->json('data');
        self::assertEquals(2, count($json));

        $response = $this->getJson(self::buildUriForKey('events', ['limit' => 1]));
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::EVENTS_STRUCTURE);
        self::assertEquals(1, count($response->json('data')));
        $response->assertJsonFragment(['type' => ServerEventType::InventorySynchronized]);
    }

    public function testFetchEventsEmpty(): void
    {
        $this->setUpAdmin();

        $response = $this->getJson(self::buildUriForKey('events'));
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::EVENTS_STRUCTURE);
        $json = $response->json('data');
        self::assertEquals(0, count($json));
    }

    public function testFetchEventsPagination(): void
    {
        $this->setUpAdmin();
        self::seedServerEvents();
        self::seedServerEvents();

        $response = $this->getJson(self::buildUriForKey('events', ['limit' => 3]));
        $response->assertJsonStructure(self::EVENTS_STRUCTURE);
        self::assertEquals(3, count($response->json('data')));
        self::assertNull($response->json('links.prev'));
        self::assertNotNull($response->json('links.next'));

        $url = $response->json('links.next');
        $response = $this->getJson($url);
        $response->assertJsonStructure(self::EVENTS_STRUCTURE);
        self::assertEquals(1, count($response->json('data')));
        self::assertNotNull($response->json('links.prev'));
        self::assertNull($response->json('links.next'));
    }

    public function testFetchEventsByType(): void
    {
        $this->setUpAdmin();
        self::seedServerEvents();

        $response = $this->getJson(
            self::buildUriForKey('events', ['filter' => ['type' => ServerEventType::HostBroadcastProcessed->value]]),
        );
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::EVENTS_STRUCTURE);
        self::assertEquals(1, count($response->json('data')));
        $response->assertJsonFragment(['type' => ServerEventType::HostBroadcastProcessed]);
    }

    public function testFetchEventsByDate(): void
    {
        $this->setUpAdmin();
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
        );
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::EVENTS_STRUCTURE);
        self::assertEquals(1, count($response->json('data')));
        $response->assertJsonFragment(['type' => ServerEventType::InventorySynchronized]);
        $response->assertJsonFragment(['test' => 2]);
    }

    public function testFetchEventsValidationLimit(): void
    {
        $this->setUpAdmin();
        $response = $this->getJson(
            self::buildUriForKey('events', ['limit' => 'no']),
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchEventsValidationTypeInvalid(): void
    {
        $this->setUpAdmin();
        $response = $this->getJson(
            self::buildUriForKey('events', ['filter' => ['type' => 'invalid']]),
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @dataProvider invalidDateProvider
     */
    public function testFetchEventsValidationFrom(string $from): void
    {
        $this->setUpAdmin();
        $response = $this->getJson(
            self::buildUriForKey('events', [
                'filter' => [
                    'createdAt' => [
                        'from' => $from,
                    ]
                ]
            ]),
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @dataProvider invalidDateProvider
     */
    public function testFetchEventsValidationTo(string $to): void
    {
        $this->setUpAdmin();
        $response = $this->getJson(
            self::buildUriForKey('events', [
                'filter' => [
                    'createdAt' => [
                        'to' => $to,
                    ]
                ]
            ]),
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
        $this->setUpAdmin();
        $response = $this->getJson(
            self::buildUriForKey('events', [
                'filter' => [
                    'createdAt' => [
                        'from' => ['2022-10-12T02:00:00+00:00'],
                    ]
                ]
            ]),
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchEventsValidationToArray(): void
    {
        $this->setUpAdmin();
        $response = $this->getJson(
            self::buildUriForKey('events', [
                'filter' => [
                    'createdAt' => [
                        'to' => ['2022-10-12T02:00:00+00:00'],
                    ]
                ]
            ]),
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchEventValidationDateRange(): void
    {
        $this->setUpAdmin();
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
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchLatestEventsByType(): void
    {
        $this->setUpAdmin();
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

    public function testFetchEventTypes(): void
    {
        $this->setUpAdmin();

        $response = $this->getJson(self::buildUriForKey('events/types'));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(['data' => []])
            ->assertJsonFragment(['BroadcastSent']);
    }

    public function testFetchUsers(): void
    {
        self::seedUsers();
        $this->setUpAdmin(User::where('is_admin', true)->first());

        $response = $this->getJson(self::buildUriForKey('users'));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        self::assertEquals(3, count($response->json('data')));
        $data = $response->json('data');
        $adminData = $data[0];
        self::assertEquals('admin@example.com', $adminData['email']);
        self::assertEquals(0, $adminData['adsharesWallet']['walletBalance']);
        self::assertEquals(0, $adminData['adsharesWallet']['bonusBalance']);
        self::assertEquals(0, $adminData['adsharesWallet']['withdrawableBalance']);
        self::assertNull($adminData['connectedWallet']['address']);
        self::assertNull($adminData['connectedWallet']['network']);
        self::assertEquals(0, $adminData['campaignCount']);
        self::assertEquals(0, $adminData['siteCount']);
        self::assertContains(Role::Admin->value, $adminData['roles']);
        self::assertFalse($adminData['isBanned']);
        self::assertNull($adminData['banReason']);
        $user1 = $data[1];
        self::assertEquals('user1@example.com', $user1['email']);
        self::assertEquals(4e5, $user1['adsharesWallet']['walletBalance']);
        self::assertEquals(2e9, $user1['adsharesWallet']['bonusBalance']);
        self::assertEquals(1e5, $user1['adsharesWallet']['withdrawableBalance']);
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
        self::assertEquals(3e11, $user2['adsharesWallet']['withdrawableBalance']);
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
        $this->setUpAdmin(User::where('is_admin', true)->first());

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
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        self::assertEquals(4, count($response->json('data')));
        self::assertNull($response->json('links.prev'));
        self::assertNotNull($response->json('links.next'));
        self::assertEquals(11, $response->json('meta.total'));
        $emails = array_map(fn($entry) => $entry['email'], $response->json(['data']));
        self::assertEquals(['user9@example.com', 'user8@example.com', 'user7@example.com', 'user6@example.com'],
            $emails);

        $url = $response->json('links.next');
        $response = $this->getJson($url);
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        self::assertEquals(4, count($response->json('data')));
        self::assertNotNull($response->json('links.prev'));
        self::assertNotNull($response->json('links.next'));
        $emails = array_map(fn($entry) => $entry['email'], $response->json(['data']));
        self::assertEquals(['user5@example.com', 'user4@example.com', 'user3@example.com', 'user12@example.com'],
            $emails);

        $url = $response->json('links.next');
        $response = $this->getJson($url);
        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        self::assertEquals(3, count($response->json('data')));
        self::assertNotNull($response->json('links.prev'));
        self::assertNull($response->json('links.next'));
        $emails = array_map(fn($entry) => $entry['email'], $response->json(['data']));
        self::assertEquals(['user11@example.com', 'user10@example.com', 'user1@example.com'], $emails);
    }

    public function testFetchUsersLimit(): void
    {
        self::seedUsers();
        $this->setUpAdmin(User::where('is_admin', true)->first());

        $response = $this->getJson(
            self::buildUriForKey('users', ['limit' => 1]),
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        self::assertEquals(1, count($response->json('data')));
        $response->assertJsonPath('data.0.email', 'admin@example.com');
    }

    public function testFetchUsersOrderByInvalidCategory(): void
    {
        self::seedUsers();
        $this->setUpAdmin(User::where('is_admin', true)->first());

        $response = $this->getJson(
            self::buildUriForKey('users', ['orderBy' => 'id']),
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testFetchUsersOrderByInvalidDirection(): void
    {
        self::seedUsers();
        $this->setUpAdmin(User::where('is_admin', true)->first());

        $response = $this->getJson(
            self::buildUriForKey('users', ['orderBy' => 'email:up']),
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @dataProvider fetchUsersOrderByProvider
     */
    public function testFetchUsersOrderBy(string $orderBy, string $expectedEmailOfFirst): void
    {
        self::seedUsers();
        $this->setUpAdmin(User::where('is_admin', true)->first());

        $response = $this->getJson(
            self::buildUriForKey('users', ['orderBy' => $orderBy . ':desc']),
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
            'withdrawableBalance' => ['withdrawableBalance', 'user2@example.com'],
            'bonusBalance & campaignCount' => ['bonusBalance:desc,campaignCount', 'user1@example.com'],
        ];
    }

    public function testFetchUsersOrderByArray(): void
    {
        self::seedUsers();
        $this->setUpAdmin(User::where('is_admin', true)->first());

        $response = $this->getJson(
            self::buildUriForKey('users', ['orderBy' => ['test1', 'test2']]),
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @dataProvider fetchUsersFilterByProvider
     */
    public function testFetchUsersFilterBy(array $filter, int $filteredCount, ?string $expectedEmailOfFirst): void
    {
        self::seedUsers();
        $this->setUpAdmin(User::where('is_admin', true)->first());

        $response = $this->getJson(
            self::buildUriForKey('users', ['filter' => $filter]),
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
        $this->setUpAdmin(User::where('is_admin', true)->first());

        $responseNoFilter = $this->getJson(
            self::buildUriForKey('users'),
        );
        $response = $this->getJson(
            self::buildUriForKey('users', ['filter' => ['role' => Role::Advertiser->value]]),
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
        $this->setUpAdmin(User::where('is_admin', true)->first());
        $query = ['filter' => ['role' => [Role::Advertiser->value, Role::Publisher->value]]];

        $response = $this->getJson(
            self::buildUriForKey('users', $query),
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
        $this->setUpAdmin(User::where('is_admin', true)->first());

        $response = $this->getJson(
            self::buildUriForKey('users', ['filter' => $filter]),
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
        $this->setUpAdmin(User::where('is_admin', true)->first());

        $response = $this->getJson(
            self::buildUriForKey('users', ['filter' => ['query' => 'user1']]),
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        self::assertEquals(1, count($response->json('data')));
        $response->assertJsonPath('data.0.email', 'user1@example.com');
    }

    public function testFetchUsersQueryByWalletAddress(): void
    {
        self::seedUsers();
        $this->setUpAdmin(User::where('is_admin', true)->first());

        $response = $this->getJson(
            self::buildUriForKey('users', ['filter' => ['query' => 'ace8d62']]),
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
        $this->setUpAdmin(User::where('is_admin', true)->first());

        $response = $this->getJson(
            self::buildUriForKey('users', ['filter' => ['query' => 'ads']]),
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        self::assertEquals(1, count($response->json('data')));
        $response->assertJsonPath('data.0.email', 'user1@example.com');
    }

    public function testFetchUsersQueryBySiteDomain(): void
    {
        self::seedUsers();
        $this->setUpAdmin(User::where('is_admin', true)->first());

        $response = $this->getJson(
            self::buildUriForKey('users', ['filter' => ['query' => 'test']]),
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        self::assertEquals(2, count($response->json('data')));
    }

    public function testFetchUsersQueryByArray(): void
    {
        self::seedUsers();
        $this->setUpAdmin(User::where('is_admin', true)->first());

        $response = $this->getJson(
            self::buildUriForKey('users', ['filter' => ['query' => ['test', 'ads']]]),
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USERS_STRUCTURE);
        self::assertEquals(1, count($response->json('data')));
        $response->assertJsonPath('data.0.email', 'user1@example.com');
    }

    public function testBanUser(): void
    {
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create(['api_token' => '1234', 'auto_withdrawal' => 1e11, 'is_banned' => 0, 'ban_reason' => null]);
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create(['user_id' => $user, 'status' => Campaign::STATUS_ACTIVE]);
        /** @var Banner $banner */
        $banner = Banner::factory()->create(['campaign_id' => $campaign, 'status' => Banner::STATUS_ACTIVE]);
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user]);
        /** @var Zone $zone */
        $zone = Zone::factory()->create(['site_id' => $site]);

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'ban'),
            ['reason' => 'suspicious activity'],
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        $user = $user->refresh();
        self::assertTrue($user->isBanned());
        self::assertEquals('suspicious activity', $user->ban_reason);
        self::assertNull($user->api_token);
        self::assertNull($user->auto_withdrawal);
        self::assertEquals(Campaign::STATUS_INACTIVE, $campaign->refresh()->status);
        self::assertEquals(Banner::STATUS_INACTIVE, $banner->refresh()->status);
        self::assertEquals(Site::STATUS_INACTIVE, $site->refresh()->status);
        self::assertEquals(Zone::STATUS_ARCHIVED, $zone->refresh()->status);
        Mail::assertQueued(UserBanned::class);
    }

    public function testBanUserFailWhileAdminBansHimself(): void
    {
        $user = $this->setUpAdmin();

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'ban'),
            ['reason' => 'suspicious activity'],
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testBanUserFailWhileModeratorBansOtherModerator(): void
    {
        $this->setUpUser(User::factory()->create(['is_moderator' => 1]));
        /** @var User $user */
        $user = User::factory()->create(['is_moderator' => 1]);

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'ban'),
            ['reason' => 'suspicious activity'],
        );

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testBanUserFailWhileUserNotExist(): void
    {
        $this->setUpAdmin();

        $response = $this->patchJson(
            self::buildUriForPatchUser(PHP_INT_MAX, 'ban'),
            ['reason' => 'suspicious activity'],
        );

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /**
     * @dataProvider banUserFailProvider
     */
    public function testBanUserFail(array $data): void
    {
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'ban'),
            $data,
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function banUserFailProvider(): array
    {
        return [
            'no reason' => [[]],
            'empty reason' => [['reason' => '']],
            'too long reason' => [['reason' => str_repeat('a', 256)]],
        ];
    }

    public function testBanUserDbException(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new RuntimeException('test-exception'));
        DB::shouldReceive('rollback')->andReturnUndefined();

        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'ban'),
            ['reason' => 'suspicious activity'],
        );

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testPatchUserConfirm(): void
    {
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create(
            [
                'admin_confirmed_at' => null,
                'email' => $this->faker->email,
                'email_confirmed_at' => new DateTimeImmutable(),
            ]
        );

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'confirm'),
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertNotNull($user->refresh()->admin_confirmed_at);
        Mail::assertQueued(UserConfirmed::class);
    }

    public function testPatchUserConfirmWithBonus(): void
    {
        $this->setUpAdmin();
        /** @var RefLink $refLink */
        $refLink = RefLink::factory()->create(['bonus' => 100, 'refund' => 0.5]);
        /** @var User $user */
        $user = User::factory()->create(
            [
                'admin_confirmed_at' => null,
                'email' => $this->faker->email,
                'email_confirmed_at' => new DateTimeImmutable(),
                'ref_link_id' => $refLink->id,
            ]
        );

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'confirm'),
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertNotNull($user->refresh()->admin_confirmed_at);
        Mail::assertQueued(UserConfirmed::class);

        self::assertSame(
            [300, 300, 0, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWithdrawableBalance(),
                $user->getWalletBalance(),
            ]
        );

        $entry = UserLedgerEntry::where('user_id', $user->id)
            ->where('type', UserLedgerEntry::TYPE_BONUS_INCOME)
            ->firstOrFail();

        $this->assertEquals(300, $entry->amount);
        $this->assertNotNull($entry->refLink);
        $this->assertEquals($refLink->id, $entry->refLink->id);
    }

    public function testDeleteUser(): void
    {
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create([
            'api_token' => '1234',
            'wallet_address' => WalletAddress::fromString('ads:0001-00000001-8B4E'),
        ]);

        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create(['user_id' => $user->id, 'status' => Campaign::STATUS_ACTIVE]);
        /** @var Banner $banner */
        $banner = Banner::factory()->create(['campaign_id' => $campaign->id, 'status' => Banner::STATUS_ACTIVE]);
        $banner->classifications()->save(BannerClassification::prepare('test_classifier'));
        /** @var ConversionDefinition $conversionDefinition */
        $conversionDefinition = Conversiondefinition::factory()->create(
            [
                'campaign_id' => $campaign->id,
                'limit_type' => 'in_budget',
                'is_repeatable' => true,
            ]
        );

        /** @var BidStrategy $bidStrategy */
        $bidStrategy = BidStrategy::factory()->create(['user_id' => $user->id]);
        $bidStrategyDetail = BidStrategyDetail::create('user:country:other', 0.2);
        $bidStrategy->bidStrategyDetails()->saveMany([$bidStrategyDetail]);

        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user->id]);
        /** @var Zone $zone */
        $zone = Zone::factory()->create(['site_id' => $site->id]);

        RefLink::factory()->create(['user_id' => $user->id]);
        Token::generate(Token::PASSWORD_CHANGE, $user, ['password' => 'qwerty123']);

        /** @var NetworkCampaign $networkCampaign */
        $networkCampaign = NetworkCampaign::factory()->create();
        /** @var NetworkBanner $networkBanner */
        $networkBanner = NetworkBanner::factory()->create(
            ['network_campaign_id' => $networkCampaign->id]
        );
        Classification::factory()->create(
            [
                'banner_id' => $networkBanner->id,
                'status' => Classification::STATUS_REJECTED,
                'site_id' => $site->id,
                'user_id' => $user->id,
            ]
        );

        $response = $this->delete(
            sprintf('%s/%d', self::buildUriForKey('users'), $user->id),
        );

        $response->assertStatus(Response::HTTP_NO_CONTENT);
        self::assertNotEmpty(User::withTrashed()->find($user->id)->deleted_at);
        self::assertNull(User::withTrashed()->find($user->id)->api_token);
        self::assertEmpty(User::withTrashed()->where('email', $user->email)->get());
        self::assertEmpty(User::withTrashed()->where('wallet_address', $user->wallet_address)->get());
        self::assertEmpty(UserSettings::where('user_id', $user->id)->get());
        self::assertNotEmpty(Campaign::withTrashed()->find($campaign->id)->deleted_at);
        self::assertNotEmpty(Banner::withTrashed()->find($banner->id)->deleted_at);
        self::assertEmpty(BannerClassification::all());
        self::assertNotEmpty(ConversionDefinition::withTrashed()->find($conversionDefinition->id)->deleted_at);
        self::assertNotEmpty(BidStrategy::withTrashed()->find($bidStrategy->id)->deleted_at);
        self::assertNotEmpty(BidStrategyDetail::withTrashed()->find($bidStrategyDetail->id)->deleted_at);
        self::assertNotEmpty(Site::withTrashed()->find($site->id)->deleted_at);
        self::assertNotEmpty(Zone::withTrashed()->find($zone->id)->deleted_at);
        self::assertEmpty(RefLink::where('user_id', $user->id)->get());
        self::assertEmpty(Token::where('user_id', $user->id)->get());
        self::assertEmpty(Classification::where('user_id', $user->id)->get());
    }

    public function testDeleteUserFailWhileAdminDeletedHimself(): void
    {
        $user = $this->setUpAdmin();

        $response = $this->delete(sprintf('%s/%d', self::buildUriForKey('users'), $user->id));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testDeleteUserFailWhileModeratorDeletesOtherModerator(): void
    {
        $this->setUpUser(User::factory()->create(['is_moderator' => 1]));
        /** @var User $user */
        $user = User::factory()->create(['is_moderator' => 1]);

        $response = $this->delete(sprintf('%s/%d', self::buildUriForKey('users'), $user->id));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteUserWhileNotExist(): void
    {
        $this->setUpAdmin();

        $response = $this->delete(
            sprintf('%s/%d', self::buildUriForKey('users'), PHP_INT_MAX),
        );

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testDeleteUserWhileDatabaseException(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new RuntimeException('test-exception'));
        DB::shouldReceive('rollback')->andReturnUndefined();
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->delete(
            sprintf('%s/%d', self::buildUriForKey('users'), $user->id),
        );

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testDenyAdvertising(): void
    {
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create(['is_advertiser' => 1]);
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create(['user_id' => $user->id, 'status' => Campaign::STATUS_ACTIVE]);
        /** @var Banner $banner */
        $banner = Banner::factory()->create(['campaign_id' => $campaign->id, 'status' => Banner::STATUS_ACTIVE]);

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'denyAdvertising'));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertNotContains('advertiser', $response->json('data.roles'));
        self::assertFalse($user->refresh()->isAdvertiser());
        self::assertEquals(Campaign::STATUS_INACTIVE, $campaign->refresh()->status);
        self::assertEquals(Banner::STATUS_INACTIVE, $banner->refresh()->status);
    }

    public function testDenyAdvertisingFailWhileDatabaseError(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new PDOException('test-exception'));
        DB::shouldReceive('rollback')->andReturnUndefined();
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create(['is_advertiser' => 1]);

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'denyAdvertising'));

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testDenyPublishing(): void
    {
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create(['is_publisher' => 1]);
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user]);
        /** @var Zone $zone */
        $zone = Zone::factory()->create(['site_id' => $site]);

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'denyPublishing'));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertNotContains('publisher', $response->json('data.roles'));
        self::assertFalse($user->refresh()->isPublisher());
        self::assertEquals(Site::STATUS_INACTIVE, $site->refresh()->status);
        self::assertEquals(Zone::STATUS_ARCHIVED, $zone->refresh()->status);
    }

    public function testDenyPublishingFailWhileDatabaseError(): void
    {
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new PDOException('test-exception'));
        DB::shouldReceive('rollback')->andReturnUndefined();
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create(['is_publisher' => 1]);

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'denyPublishing'));

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testPatchUserGrantAdvertising(): void
    {
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create(['is_advertiser' => 0]);

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'grantAdvertising'),
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertContains('advertiser', $response->json('data.roles'));
        self::assertTrue($user->refresh()->isAdvertiser());
    }

    public function testPatchUserGrantPublishing(): void
    {
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create(['is_publisher' => 0]);

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'grantPublishing'),
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertContains('publisher', $response->json('data.roles'));
        self::assertTrue($user->refresh()->isPublisher());
    }

    public function testSwitchUserToAdmin(): void
    {
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create(['is_admin' => 0, 'is_publisher' => 0]);

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'switchToAdmin'));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertTrue($user->refresh()->isAdmin());
        self::assertTrue($user->isAdvertiser());
        self::assertTrue($user->isPublisher());
    }

    public function testSwitchUserToAdminFailWhileUserIsNotRegularType(): void
    {
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create(['is_moderator' => 1]);

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'switchToAdmin'));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSwitchUserToAdminFailWhileUserHasCampaign(): void
    {
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create();
        Campaign::factory()->create(['user_id' => $user]);

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'switchToAdmin'));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSwitchUserToAgency(): void
    {
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create(['is_agency' => 0]);

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'switchToAgency'));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertTrue($user->refresh()->isAgency());
    }

    public function testSwitchUserToAgencyByRegularUser(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['is_agency' => 0]);
        $this->setUpAdmin($user);

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'switchToAgency'));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        self::assertFalse($user->refresh()->isAgency());
    }

    public function testSwitchUserToAgencyWhileUserIsNotRegularType(): void
    {
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create(['is_agency' => 1]);

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'switchToAgency'));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSwitchUserToAgencyWhileUserDeleted(): void
    {
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create([
            'deleted_at' => new DateTimeImmutable('-1 minute'),
            'is_agency' => 0,
        ]);

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'switchToAgency'));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
        self::assertFalse($user->refresh()->isAgency());
    }

    public function testSwitchUserToModerator(): void
    {
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create(['is_moderator' => 0, 'is_publisher' => 0]);

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'switchToModerator'));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertTrue($user->refresh()->isModerator());
        self::assertTrue($user->isAdvertiser());
        self::assertTrue($user->isPublisher());
    }

    public function testSwitchUserToModeratorByModerator(): void
    {
        $this->setUpAdmin(User::factory()->create(['is_moderator' => 1]));
        /** @var User $user */
        $user = User::factory()->create(['is_moderator' => 0]);

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'switchToModerator'));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        self::assertFalse($user->refresh()->isModerator());
    }

    public function testSwitchUserToModeratorFailWhileUserIsNotRegularType(): void
    {
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create(['is_agency' => 1]);

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'switchToModerator'));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSwitchUserToModeratorFailWhileUserHasSite(): void
    {
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create();
        Site::factory()->create(['user_id' => $user]);

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'switchToModerator'));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSwitchUserToModeratorWhileUserDeleted(): void
    {
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create([
            'deleted_at' => new DateTimeImmutable('-1 minute'),
            'is_moderator' => 0,
        ]);

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'switchToModerator'));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
        self::assertFalse($user->refresh()->isModerator());
    }

    public function testSwitchUserToRegular(): void
    {
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create(['is_moderator' => 1]);

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'switchToRegular'));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertFalse($user->refresh()->isModerator());
    }

    public function testSwitchUserToRegularByRegularUser(): void
    {
        $this->setUpAdmin(User::factory()->create());
        /** @var User $moderator */
        $moderator = User::factory()->create(['is_moderator' => 1]);

        $response = $this->patchJson(self::buildUriForPatchUser($moderator->id, 'switchToRegular'));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        self::assertTrue($moderator->refresh()->isModerator());
    }

    public function testSwitchUserToRegularByModeratorWhileUserIsModerator(): void
    {
        $this->setUpAdmin(User::factory()->create(['is_moderator' => 1]));
        /** @var User $user */
        $user = User::factory()->create(['is_moderator' => 1]);

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'switchToRegular'));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        self::assertTrue($user->refresh()->isModerator());
    }

    public function testSwitchUserToRegularByAdminWhoChangesHimself(): void
    {
        $user = $this->setUpAdmin();

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'switchToRegular'));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSwitchUserToRegularWhileUserDeleted(): void
    {
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create([
            'deleted_at' => new DateTimeImmutable('-1 minute'),
            'is_moderator' => 1,
        ]);

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'switchToRegular'));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
        self::assertTrue($user->refresh()->isModerator());
    }

    public function testUnbanUser(): void
    {
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create(['is_banned' => 1, 'ban_reason' => 'suspicious activity']);

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'unban'));

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        $user = $user->refresh();
        self::assertFalse($user->isBanned());
    }

    public function testUnbanUserFailWhileAdminUnbansHimself(): void
    {
        $user = $this->setUpAdmin();

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'unban'));

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUnbanUserFailWhileModeratorBansOtherModerator(): void
    {
        $this->setUpUser(User::factory()->create(['is_moderator' => 1]));
        /** @var User $user */
        $user = User::factory()->create(['is_moderator' => 1]);

        $response = $this->patchJson(self::buildUriForPatchUser($user->id, 'unban'));

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testUnbanUserWhileNotExistingUser(): void
    {
        $this->setUpAdmin();

        $response = $this->patchJson(self::buildUriForPatchUser(PHP_INT_MAX, 'unban'));

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testPatchUserInvalidAction(): void
    {
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->patchJson(
            self::buildUriForPatchUser($user->id, 'invalid'),
        );
        $response->assertStatus(Response::HTTP_NOT_FOUND);
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
        UserLedgerEntry::factory()->create([
            'user_id' => $user1->id,
            'type' => UserLedgerEntry::TYPE_NON_WITHDRAWABLE_DEPOSIT,
            'amount' => 3e5,
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
        $this->setUpAdmin();
        $response = $this->postJson(self::buildUriForKey('users'), self::getUserData());

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
        $this->setUpAdmin();
        Config::updateAdminSettings([Config::EMAIL_VERIFICATION_REQUIRED => '1']);
        $response = $this->postJson(
            self::buildUriForKey('users'),
            self::getUserData([], 'forcePasswordChange'),
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
        $this->setUpAdmin();
        Config::updateAdminSettings([Config::EMAIL_VERIFICATION_REQUIRED => '0']);
        $response = $this->postJson(
            self::buildUriForKey('users'),
            self::getUserData([], 'forcePasswordChange'),
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
        $this->setUpAdmin();
        User::factory()->create([
            'email' => 'user5@example.com',
            'wallet_address' => new WalletAddress(WalletAddress::NETWORK_ADS, '0001-00000004-DBEB'),
        ]);
        $response = $this->postJson(self::buildUriForKey('users'), $data);

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
        ];
    }

    public function testAddUserWhileDbError(): void
    {
        $this->setUpAdmin();
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new RuntimeException('test-exception'));
        DB::shouldReceive('rollback')->andReturnUndefined();

        $response = $this->postJson(self::buildUriForKey('users'), self::getUserData());

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    private static function getUserData(array $mergeData = [], string $remove = null): array
    {
        $data = array_merge([
            'email' => 'user@example.com',
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
        $this->setUpAdmin();
        Config::updateAdminSettings([Config::EMAIL_VERIFICATION_REQUIRED => '1']);
        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'email_confirmed_at' => new DateTimeImmutable('-10 days'),
        ]);
        $data = ['email' => 'user2@example.com'];

        $response = $this->patchJson(self::buildUriForPatchUser($user->id), $data);

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
        $this->setUpAdmin();
        Config::updateAdminSettings([Config::EMAIL_VERIFICATION_REQUIRED => '0']);
        /** @var User $user */
        $user = User::factory()->create(['email' => 'user@example.com']);
        $data = ['email' => 'user2@example.com'];

        $response = $this->patchJson(self::buildUriForPatchUser($user->id), $data);

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
        $this->setUpAdmin();
        /** @var User $user */
        $user = User::factory()->create();
        $data = [
            'wallet' => [
                'address' => '0001-00000001-8B4E',
                'network' => WalletAddress::NETWORK_ADS,
            ],
        ];

        $response = $this->patchJson(self::buildUriForPatchUser($user->id), $data);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::USER_STRUCTURE);
        self::assertDatabaseHas(User::class, [
            'wallet_address' => (new WalletAddress(WalletAddress::NETWORK_ADS, '0001-00000001-8B4E'))->toString(),
        ]);
    }

    public function testEditUserWalletAddressFailWhileModeratorEditsOtherModerator(): void
    {
        $this->setUpUser(User::factory()->create(['is_moderator' => 1]));
        /** @var User $user */
        $user = User::factory()->create(['is_moderator' => 1]);
        $data = [
            'wallet' => [
                'address' => '0001-00000001-8B4E',
                'network' => WalletAddress::NETWORK_ADS,
            ],
        ];

        $response = $this->patchJson(self::buildUriForPatchUser($user->id), $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testEditUserInvalid(): void
    {
        $this->setUpAdmin();
        $data = ['email' => 'user2@example.com'];

        $response = $this->patchJson(self::buildUriForPatchUser(PHP_INT_MAX), $data);

        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /**
     * @dataProvider editUserInvalidDataProvider
     */
    public function testEditUserWhileDataInvalid(array $data): void
    {
        $this->setUpAdmin();
        User::factory()->create([
            'email' => 'user2@example.com',
            'wallet_address' => new WalletAddress(WalletAddress::NETWORK_ADS, '0001-00000001-8B4E'),
        ]);
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->patchJson(self::buildUriForPatchUser($user->id), $data);

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
            'invalid wallet' => [['wallet' => ['address' => 'invalid', 'network' => WalletAddress::NETWORK_ADS]]],
        ];
    }

    public function testEditUserWhileDbError(): void
    {
        $this->setUpAdmin();
        DB::shouldReceive('beginTransaction')->andReturnUndefined();
        DB::shouldReceive('commit')->andThrow(new RuntimeException('test-exception'));
        DB::shouldReceive('rollback')->andReturnUndefined();
        /** @var User $user */
        $user = User::factory()->create();
        $data = ['email' => 'user2@example.com'];

        $response = $this->patchJson(self::buildUriForPatchUser($user->id), $data);

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    private function setUpAdmin(User $user = null): User
    {
        if (null === $user) {
            $user = User::factory()->admin()->create();
        }
        Passport::actingAs($user, [], 'jwt');
        return $user;
    }

    private function setUpUser(User $user = null): void
    {
        if (null === $user) {
            $user = User::factory()->create();
        }
        Passport::actingAs($user, [], 'jwt');
    }

    private function seedTurnoverData(): void
    {
        TurnoverEntry::factory()
            ->count(9)
            ->sequence(
                [
                    'amount' => 200_000_000_000,
                    'hour_timestamp' => '2023-04-11 13:00:00',
                    'type' => TurnoverEntryType::DspAdvertisersExpense,
                ],
                [
                    'ads_address' => '0001-00000024-FF89',
                    'amount' => 2_000_000_000,
                    'hour_timestamp' => '2023-04-11 13:00:00',
                    'type' => TurnoverEntryType::DspLicenseFee,
                ],
                [
                    'amount' => 19_800_000_000,
                    'hour_timestamp' => '2023-04-11 13:00:00',
                    'type' => TurnoverEntryType::DspOperatorFee,
                ],
                [
                    'ads_address' => '0001-00000001-8B4E',
                    'amount' => 1_782_000_000,
                    'hour_timestamp' => '2023-04-11 13:00:00',
                    'type' => TurnoverEntryType::DspCommunityFee,
                ],
                [
                    'ads_address' => '0001-00000002-BB2D',
                    'amount' => 76_018_000_000,
                    'hour_timestamp' => '2023-04-11 13:00:00',
                    'type' => TurnoverEntryType::DspExpense,
                ],
                [
                    'ads_address' => '0001-00000003-AB0C',
                    'amount' => 100_400_000_000,
                    'hour_timestamp' => '2023-04-11 13:00:00',
                    'type' => TurnoverEntryType::DspExpense,
                ],
                [
                    'ads_address' => '0001-00000003-AB0C',
                    'amount' => 2_000,
                    'hour_timestamp' => '2023-04-11 13:00:00',
                    'type' => TurnoverEntryType::SspIncome,
                ],
                [
                    'amount' => 500,
                    'hour_timestamp' => '2023-04-11 13:00:00',
                    'type' => TurnoverEntryType::SspOperatorFee,
                ],
                [
                    'amount' => 1_500,
                    'hour_timestamp' => '2023-04-11 13:00:00',
                    'type' => TurnoverEntryType::SspPublishersIncome,
                ]
            )->create();
    }
}
