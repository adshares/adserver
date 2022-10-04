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

use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Supply\Domain\ValueObject\HostStatus;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

final class ServerMonitoringControllerTest extends TestCase
{
    private const MONITORING_URI = '/api/monitoring';
    private const HOSTS_STRUCTURE = [
        'hosts' => [
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
            ],
        ]
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
}
