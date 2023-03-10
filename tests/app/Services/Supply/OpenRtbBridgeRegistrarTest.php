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

declare(strict_types=1);

namespace Adshares\Adserver\Tests\Services\Supply;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Services\Supply\OpenRtbBridgeRegistrar;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\Url;
use Adshares\Config\AppMode;
use Adshares\Config\RegistrationMode;
use Adshares\Mock\Client\DummyDemandClient;
use Adshares\Supply\Application\Dto\Info;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use PHPUnit\Framework\MockObject\MockObject;

class OpenRtbBridgeRegistrarTest extends TestCase
{
    public function testRegisterAsNetworkHost(): void
    {
        $this->initOpenRtb();
        $registrar = new OpenRtbBridgeRegistrar($this->getDemandClient());

        $result = $registrar->registerAsNetworkHost();

        self::assertTrue($result);
        self::assertDatabaseHas(NetworkHost::class, [
            'address' => '0001-00000004-DBEB',
            'host' => 'https://app.example.com',
        ]);
    }

    public function testRegisterAsNetworkHostFailWhileInvalidResponse(): void
    {
        $this->initOpenRtb();
        $clientMock = self::createMock(DemandClient::class);
        $clientMock->method('fetchInfo')->willThrowException(new UnexpectedClientResponseException('test-exception'));
        $registrar = new OpenRtbBridgeRegistrar($clientMock);

        $result = $registrar->registerAsNetworkHost();

        self::assertFalse($result);
    }

    public function testRegisterAsNetworkHostFailWhileNoConfiguration(): void
    {
        $registrar = new OpenRtbBridgeRegistrar($this->getDemandClient());

        $result = $registrar->registerAsNetworkHost();

        self::assertFalse($result);
    }

    public function testRegisterAsNetworkHostFailWhileInvalidConfigurationAddress(): void
    {
        $this->initOpenRtb([Config::OPEN_RTB_BRIDGE_ACCOUNT_ADDRESS => '0001-00000004']);
        $registrar = new OpenRtbBridgeRegistrar($this->getDemandClient());

        $result = $registrar->registerAsNetworkHost();

        self::assertFalse($result);
    }

    public function testRegisterAsNetworkHostFailWhileInvalidConfigurationUrl(): void
    {
        $this->initOpenRtb([Config::OPEN_RTB_BRIDGE_URL => 'example.com']);
        $registrar = new OpenRtbBridgeRegistrar($this->getDemandClient());

        $result = $registrar->registerAsNetworkHost();

        self::assertFalse($result);
    }

    public function testRegisterAsNetworkHostFailWhileInfoForAdserver(): void
    {
        $this->initOpenRtb();
        $registrar = new OpenRtbBridgeRegistrar(new DummyDemandClient());

        $result = $registrar->registerAsNetworkHost();

        self::assertFalse($result);
        self::assertDatabaseMissing(NetworkHost::class, [
            'address' => '0001-00000004-DBEB',
            'host' => 'https://app.example.com',
        ]);
    }

    public function testRegisterAsNetworkHostFailWhileInfoForDifferentAddress(): void
    {
        $this->initOpenRtb();
        $info = new Info(
            'openrtb',
            'OpenRTB Provider ',
            '0.1.0',
            new Url('https://app.example.com'),
            new Url('https://panel.example.com'),
            new Url('https://example.com'),
            new Url('https://example.com/privacy'),
            new Url('https://example.com/terms'),
            new Url('https://example.com/inventory'),
            new AccountId('0001-00000005-CBCA'),
            null,
            [Info::CAPABILITY_ADVERTISER],
            RegistrationMode::PRIVATE,
            AppMode::OPERATIONAL
        );
        $clientMock = self::createMock(DemandClient::class);
        $clientMock->method('fetchInfo')->willReturn($info);
        $registrar = new OpenRtbBridgeRegistrar($clientMock);

        $result = $registrar->registerAsNetworkHost();

        self::assertFalse($result);
        self::assertDatabaseMissing(NetworkHost::class, [
            'address' => '0001-00000004-DBEB',
            'host' => 'https://app.example.com',
        ]);
    }

    private function getDemandClient(): MockObject|DemandClient
    {
        $info = new Info(
            'openrtb',
            'OpenRTB Provider ',
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
            AppMode::OPERATIONAL
        );
        $clientMock = self::createMock(DemandClient::class);
        $clientMock->method('fetchInfo')->willReturn($info);
        return $clientMock;
    }

    private function initOpenRtb(array $settings = []): void
    {
        $mergedSettings = array_merge(
            [
                Config::OPEN_RTB_BRIDGE_ACCOUNT_ADDRESS => '0001-00000004-DBEB',
                Config::OPEN_RTB_BRIDGE_URL => 'https://example.com',
            ],
            $settings,
        );
        Config::updateAdminSettings($mergedSettings);
        DatabaseConfigReader::overwriteAdministrationConfig();
    }
}
