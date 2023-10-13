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
use Adshares\Adserver\Services\Supply\DspBridgeRegistrar;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Config\RegistrationMode;
use Adshares\Mock\Client\DummyDemandClient;
use Adshares\Supply\Application\Dto\Info;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use PHPUnit\Framework\MockObject\MockObject;

class DspBridgeRegistrarTest extends TestCase
{
    public function testRegisterAsNetworkHost(): void
    {
        $this->initDspBridge();
        $registrar = new DspBridgeRegistrar($this->getDemandClient());

        $result = $registrar->registerAsNetworkHost();

        self::assertTrue($result);
        self::assertDatabaseHas(NetworkHost::class, [
            'address' => '0001-00000004-DBEB',
            'host' => 'https://app.example.com',
        ]);
    }

    public function testRegisterAsNetworkHostFailWhileInvalidResponse(): void
    {
        $this->initDspBridge();
        $clientMock = self::createMock(DemandClient::class);
        $clientMock->method('fetchInfo')->willThrowException(new UnexpectedClientResponseException('test-exception'));
        $registrar = new DspBridgeRegistrar($clientMock);

        $result = $registrar->registerAsNetworkHost();

        self::assertFalse($result);
    }

    public function testRegisterAsNetworkHostFailWhileNoConfiguration(): void
    {
        $registrar = new DspBridgeRegistrar($this->getDemandClient());

        $result = $registrar->registerAsNetworkHost();

        self::assertFalse($result);
    }

    public function testRegisterAsNetworkHostFailWhileInvalidConfigurationAddress(): void
    {
        $this->initDspBridge([Config::DSP_BRIDGE_ACCOUNT_ADDRESS => '0001-00000004']);
        $registrar = new DspBridgeRegistrar($this->getDemandClient());

        $result = $registrar->registerAsNetworkHost();

        self::assertFalse($result);
    }

    public function testRegisterAsNetworkHostFailWhileInvalidConfigurationUrl(): void
    {
        $this->initDspBridge([Config::DSP_BRIDGE_URL => 'example.com']);
        $registrar = new DspBridgeRegistrar($this->getDemandClient());

        $result = $registrar->registerAsNetworkHost();

        self::assertFalse($result);
    }

    public function testRegisterAsNetworkHostFailWhileInfoForAdserver(): void
    {
        $this->initDspBridge();
        $registrar = new DspBridgeRegistrar(new DummyDemandClient());

        $result = $registrar->registerAsNetworkHost();

        self::assertFalse($result);
        self::assertDatabaseMissing(NetworkHost::class, [
            'address' => '0001-00000004-DBEB',
            'host' => 'https://app.example.com',
        ]);
    }

    public function testRegisterAsNetworkHostFailWhileInfoForDifferentAddress(): void
    {
        $this->initDspBridge();
        $registrar = new DspBridgeRegistrar($this->getDemandClient('0001-00000005-CBCA'));

        $result = $registrar->registerAsNetworkHost();

        self::assertFalse($result);
        self::assertDatabaseMissing(NetworkHost::class, [
            'address' => '0001-00000004-DBEB',
            'host' => 'https://app.example.com',
        ]);
    }

    private function getDemandClient(string $address = '0001-00000004-DBEB'): MockObject|DemandClient
    {
        $info = Info::fromArray(
            [
                'module' => 'dsp-bridge',
                'name' => 'DSP bridge',
                'version' => '0.1.0',
                'serverUrl' => 'https://app.example.com',
                'panelUrl' => 'https://panel.example.com',
                'privacyUrl' => 'https://example.com/privacy',
                'termsUrl' => 'https://example.com/terms',
                'inventoryUrl' => 'https://example.com/inventory',
                'adsAddress' => $address,
                'capabilities' => [Info::CAPABILITY_ADVERTISER],
                'registrationMode' => RegistrationMode::PRIVATE,
            ],
        );
        $clientMock = self::createMock(DemandClient::class);
        $clientMock->method('fetchInfo')->willReturn($info);
        return $clientMock;
    }

    private function initDspBridge(array $settings = []): void
    {
        $mergedSettings = array_merge(
            [
                Config::DSP_BRIDGE_ACCOUNT_ADDRESS => '0001-00000004-DBEB',
                Config::DSP_BRIDGE_URL => 'https://example.com',
            ],
            $settings,
        );
        Config::updateAdminSettings($mergedSettings);
        DatabaseConfigReader::overwriteAdministrationConfig();
    }
}
