<?php
// phpcs:ignoreFile PHPCompatibility.Miscellaneous.ValidIntegers.HexNumericStringFound

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

namespace Adshares\Mock\Client;

use Adshares\Common\Application\Dto\Gateway;
use Adshares\Common\Application\Service\AdsRpcClient;
use RuntimeException;

class DummyAdsRpcClient implements AdsRpcClient
{
    private const GATEWAYS = [
        [
            'address' => '0001-00000002-BB2D',
            'chain_id' => 1,
            'code' => 'ETH',
            'contract_address' => '0xcfcEcFe2bD2FED07A9145222E8a7ad9Cf1Ccd22A',
            'description' => null,
            'format' => 'eth',
            'name' => 'Ethereum',
            'prefix' => '000000575241505F4554483A'
        ],
        [
            'address' => '0001-00000003-AB0C',
            'chain_id' => 56,
            'code' => 'BSC',
            'contract_address' => '0xcfcEcFe2bD2FED07A9145222E8a7ad9Cf1Ccd22A',
            'description' => null,
            'format' => 'eth',
            'name' => 'Binance Smart Chain',
            'prefix' => '000000575241505F4253433A'
        ]
    ];

    public function getGateway(string $code): Gateway
    {
        foreach ($this->getGateways() as $gateway) {
            if (strtoupper($code) === $gateway->getCode()) {
                return $gateway;
            }
        }
        throw new RuntimeException(sprintf('Cannot find gateway "%s"', $code));
    }

    public function getGateways(): array
    {
        return array_map(fn($data) => Gateway::fromArray($data), self::GATEWAYS);
    }
}
