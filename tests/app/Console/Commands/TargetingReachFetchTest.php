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

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\NetworkVectorsMeta;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\Url;
use Adshares\Config\AppMode;
use Adshares\Config\RegistrationMode;
use Adshares\Supply\Application\Dto\Info;
use Adshares\Supply\Application\Service\SupplyClient;

class TargetingReachFetchTest extends ConsoleTestCase
{
    private const COMMAND_SIGNATURE = 'ops:targeting-reach:fetch';

    public function testLock(): void
    {
        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(1);
    }

    public function testHandleWhileOnlyLocalNetworkHost(): void
    {
        $supplyClient = self::createMock(SupplyClient::class);
        $supplyClient->expects(self::never())->method('fetchTargetingReach');
        $this->app->bind(SupplyClient::class, fn() => $supplyClient);
        NetworkHost::factory()->create([
            'address' => '0001-00000005-CBCA',
        ]);

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(0);

        self::assertDatabaseEmpty(NetworkVectorsMeta::class);
        self::assertDatabaseEmpty('network_vectors');
    }

    public function testHandleWhileNetworkHostIsDspOnly(): void
    {
        $supplyClient = self::createMock(SupplyClient::class);
        $supplyClient->expects(self::never())->method('fetchTargetingReach');
        $this->app->bind(SupplyClient::class, fn() => $supplyClient);
        $info = new Info(
            'dsp-bridge',
            'DSP bridge',
            '0.1.0',
            new Url('https://app.example.com'),
            new Url('https://panel.example.com'),
            new Url('https://example.com'),
            new Url('https://example.com/privacy'),
            new Url('https://example.com/terms'),
            new Url('https://example.com/inventory'),
            new AccountId('0001-00000004-DBEB'),
            null,
            [Info::CAPABILITY_ADVERTISER],
            RegistrationMode::PRIVATE,
            AppMode::OPERATIONAL,
            'example.com',
            false,
        );
        NetworkHost::factory()->create([
            'address' => '0001-00000004-DBEB',
            'info' => $info,
        ]);

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(0);

        self::assertDatabaseEmpty(NetworkVectorsMeta::class);
        self::assertDatabaseEmpty('network_vectors');
    }

    public function testHandleWhileNoVectors(): void
    {
        /** @var NetworkHost $networkHost */
        $networkHost = NetworkHost::factory()->create([
            'address' => '0001-00000004-DBEB',
        ]);

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(0);

        self::assertDatabaseHas(
            NetworkVectorsMeta::class,
            [
                'network_host_id' => $networkHost->id,
                'total_events_count' => 0,
            ]
        );
        self::assertDatabaseEmpty('network_vectors');
    }
}
