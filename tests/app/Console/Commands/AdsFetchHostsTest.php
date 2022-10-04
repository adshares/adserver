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

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Ads\AdsClient;
use Adshares\Ads\Command\GetBroadcastCommand;
use Adshares\Ads\Driver\CommandError;
use Adshares\Ads\Exception\CommandException;
use Adshares\Ads\Response\GetBroadcastResponse;
use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Config\AppMode;
use Adshares\Supply\Application\Dto\Info;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Adshares\Supply\Domain\ValueObject\HostStatus;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;

class AdsFetchHostsTest extends ConsoleTestCase
{
    private const COMMAND_SIGNATURE = 'ads:fetch-hosts';

    public function testLock(): void
    {
        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(0);
    }

    public function testFetchingHosts(): void
    {
        $this->setupAdsClient();
        $this->setupDemandClientInfo(self::getInfoData());

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(0);
        $host = NetworkHost::fetchByAddress('0001-00000001-8B4E');
        $this->assertNotNull($host);
        $this->assertEquals('https://app.example.com/', $host->host);
    }

    public function testUpdatingHosts(): void
    {
        $this->setupAdsClient();
        $this->setupDemandClientInfo(self::getInfoData());

        NetworkHost::factory()->create(['address' => '0001-00000002-BB2D', 'host' => 'https://old.example.com/']);

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(0);
        $host = NetworkHost::fetchByAddress('0001-00000001-8B4E');
        $this->assertNotNull($host);
        $this->assertEquals('https://app.example.com/', $host->host);
    }

    public function testRestoringHosts(): void
    {
        $this->setupAdsClient();
        $this->setupDemandClientInfo(self::getInfoData());

        NetworkHost::factory()->create(
            [
                'address' => '0001-00000001-8B4E',
                'host' => 'https://app.example.com/',
                'deleted_at' => new DateTimeImmutable()
            ]
        );

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(0);
        $host = NetworkHost::fetchByAddress('0001-00000001-8B4E');
        $this->assertNotNull($host);
        $this->assertEquals('https://app.example.com/', $host->host);
    }

    public function testDeletingOldHosts(): void
    {
        $this->setupAdsClient();
        $this->setupDemandClientInfo(self::getInfoData());

        NetworkHost::factory()->create(
            [
                'address' => '0001-00000003-AB0C',
                'host' => 'https://two.example.com/',
                'last_broadcast' => new DateTimeImmutable('-3 days')
            ]
        );
        NetworkHost::factory()->create(['address' => '0001-00000002-BB2D', 'host' => 'https://app.example.com/']);

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(0);

        $host = NetworkHost::fetchByAddress('0001-00000001-8B4E');
        $this->assertNotNull($host);
        $this->assertEquals('https://app.example.com/', $host->host);
        $this->assertNull(NetworkHost::fetchByAddress('0001-00000003-AB0C'));
    }

    public function testFetchingHostsDemandClientException(): void
    {
        $this->setupAdsClient();
        $this->setupDemandClientThrowingException();

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(0);
        self::assertDatabaseCount(NetworkHost::class, 0);
    }

    public function testFetchingHostsDemandClientInvalidInfoModule(): void
    {
        $this->setupAdsClient();
        $this->setupDemandClientInfo(self::getInfoData(['module' => 'invalid']));

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(0);
        self::assertDatabaseCount(NetworkHost::class, 0);
    }

    public function invalidInfoDataProvider(): array
    {
        return [
            'no module' => [self::getInfoData([], 'module')],
            'invalid module' => [],
        ];
    }

    /**
     * @dataProvider invalidInfoProvider
     */
    public function testFetchingHostsDemandClientInvalidInfo(array $infoData): void
    {
        $this->setupAdsClient();
        $this->setupDemandClientInfo($infoData);

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(0);
        self::assertDatabaseHas(
            NetworkHost::class,
            [
                'address' => '0001-00000001-8B4E',
                'status' => HostStatus::Failure,
            ],
        );
    }

    public function invalidInfoProvider(): array
    {
        return [
            'no address' => [self::getInfoData([], 'adsAddress')],
            'invalid address' => [self::getInfoData(['adsAddress' => '0001-00000002-BB2D'])],
            'invalid ad server mode' => [self::getInfoData(['mode' => AppMode::INITIALIZATION])],
        ];
    }

    public function testBroadcastNotReady(): void
    {
        Log::shouldReceive('info');
        Log::shouldReceive('warning')
            ->atLeast()->once()
            ->withArgs(fn($message) => str_contains($message, 'Broadcast not ready'));
        $errorCode = CommandError::BROADCAST_NOT_READY;
        $this->setupFailingAdsClient($errorCode);

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(0);
    }

    public function testBroadcastError(): void
    {
        Log::shouldReceive('info');
        Log::shouldReceive('error')
            ->atLeast()->once()
            ->withArgs(fn($message) => str_contains($message, 'Unexpected error'));
        $errorCode = CommandError::UNKNOWN_ERROR;
        $this->setupFailingAdsClient($errorCode);

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(0);
    }

    private function setupAdsClient(): void
    {
        $this->app->bind(
            AdsClient::class,
            function () {
                $i = 0;
                $clientMock = self::createMock(AdsClient::class);
                $clientMock->method('getBroadcast')
                    ->will(
                        self::returnCallback(
                            function () use (&$i) {
                                if (0 === $i++) {
                                    $data = $this->getRawBroadcastData();
                                    $data['broadcast'][0]['time'] = (string)time();
                                } else {
                                    $data = $this->getRawEmptyBroadcastData();
                                }
                                return new GetBroadcastResponse($data);
                            }
                        )
                    );

                return $clientMock;
            }
        );
    }

    // phpcs:disable Generic.Files.LineLength
    private function getRawBroadcastData(): array
    {
        return json_decode(
            <<<RAW
        {
            "current_block_time": "1656978900",
            "previous_block_time": "1656978800",
            "tx": {
                "data": "120400A0050000007EC36295FAC362",
                "signature": "5B62469D47C645E5BBB13351A8AA48752578836C5A875778C53D4F9DA0E3C8664DBE06E456E13FF30C860EE07CD569B4A04E5150B406FD899EAA3EE4F900FF05",
                "time": "1657010837"
            },
            "block_time_hex": "62C37B00",
            "block_time": "1656978900",
            "broadcast_count": "1",
            "log_file": "new",
            "broadcast": [
                {
                    "block_time": "1656939202",
                    "block_date": "2022-07-04 01:00:00",
                    "node": "1",
                    "account": "1",
                    "address": "0001-00000001-8B4E",
                    "account_msid": "4",
                    "time": "1656939202",
                    "date": "2022-07-04 01:00:00",
                    "data": "030100F1000000A9AA0000027FC3622100",
                    "message": "68747470733A2F2F6170702E6578616D706C652E636F6D2F696E666F2E6A736F6E",
                    "signature": "5EF8108BBBCD8011EC10A8743936FED3B8E05616939A48E9C8FB0627C2F2AF76D363AC073095237DDC6D53D2CB7B189A2A71B215FECD7817BED1F829227D450B",
                    "input_hash": "AB85A438C4CFCB48BD9CD3E0818D1057A663992AEDD731F6450CA400527E8E4F",
                    "public_key": "ABD78088F63018811144C9809BB62AA5D8A06DFA81548045BDD3691461E6910B",
                    "verify": "failed",
                    "node_msid": "7",
                    "node_mpos": "1",
                    "id": "0001:00000007:0001",
                    "fee": "0.00000011000"
                }
            ]
        }
        RAW,
            true
        );
    }

    private function getRawEmptyBroadcastData(): array
    {
        return json_decode(
            <<<RAW
        {
            "current_block_time": "1656978900",
            "previous_block_time": "1656978800",
            "tx": {
                "data": "120400A0050000007EC36295FAC362",
                "signature": "5B62469D47C645E5BBB13351A8AA48752578836C5A875778C53D4F9DA0E3C8664DBE06E456E13FF30C860EE07CD569B4A04E5150B406FD899EAA3EE4F900FF05",
                "time": "1657010837"
            },
            "block_time_hex": "62C37B00",
            "block_time": "1656978900",
            "broadcast_count": "0",
            "log_file": "new",
            "broadcast": [
            ]
        }
        RAW,
            true
        );
    }

    // phpcs:enable

    private function setupDemandClientInfo(array $infoData): void
    {
        $this->app->bind(
            DemandClient::class,
            function () use ($infoData) {
                $mock = self::createMock(DemandClient::class);
                $mock->method('fetchInfo')
                    ->willReturn(Info::fromArray($infoData));

                return $mock;
            }
        );
    }

    private function setupDemandClientThrowingException(): void
    {
        $this->app->bind(
            DemandClient::class,
            function () {
                $mock = self::createMock(DemandClient::class);
                $mock->method('fetchInfo')
                    ->willThrowException(new UnexpectedClientResponseException('test-exception'));

                return $mock;
            }
        );
    }

    private function setupFailingAdsClient(int $errorCode): void
    {
        $this->app->bind(
            AdsClient::class,
            function () use ($errorCode) {
                $mock = self::createMock(AdsClient::class);
                $mock->method('getBroadcast')
                    ->willThrowException(
                        new CommandException(
                            new GetBroadcastCommand(),
                            'Test command exception',
                            $errorCode
                        )
                    );

                return $mock;
            }
        );
    }

    private static function getInfoData(array $mergeData = [], string $remove = null): array
    {
        $data = array_merge(
            [
                'adsAddress' => '0001-00000001-8B4E',
                'module' => 'adserver',
                'name' => 'Test AdServer',
                'version' => '0.1',
                'serverUrl' => 'https://app.example.com/',
                'panelUrl' => 'https://example.com/',
                'privacyUrl' => 'https://app.example.com/privacy',
                'termsUrl' => 'https://app.example.com/terms',
                'inventoryUrl' => 'https://app.example.com/import',
                'capabilities' => [Info::CAPABILITY_PUBLISHER, Info::CAPABILITY_ADVERTISER],
            ],
            $mergeData
        );

        if ($remove !== null) {
            unset($data[$remove]);
        }

        return $data;
    }

    private static function getInfo(): Info
    {
        return Info::fromArray(self::getInfoData());
    }
}
