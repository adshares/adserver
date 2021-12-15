<?php

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
// phpcs:ignoreFile PHPCompatibility.Miscellaneous.ValidIntegers.HexNumericStringFound

namespace Adshares\Tests\Common\Domain\ValueObject;

use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Common\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WalletAddressTest extends TestCase
{
    public function testConstruct(): void
    {
        $address1 = new WalletAddress('ADS', '0001-00000001-8B4e');
        $this->assertEquals(WalletAddress::NETWORK_ADS, $address1->getNetwork());
        $this->assertEquals('0001-00000001-8B4E', $address1->getAddress());
        $this->assertEquals('ads:0001-00000001-8B4E', (string)$address1);

        $address2 = new WalletAddress('BSC', '0xCFCECFE2BD2FED07A9145222E8A7AD9CF1CCD22A');
        $this->assertEquals(WalletAddress::NETWORK_BSC, $address2->getNetwork());
        $this->assertEquals('0xcfcecfe2bd2fed07a9145222e8a7ad9cf1ccd22a', $address2->getAddress());
        $this->assertEquals('bsc:0xcfcecfe2bd2fed07a9145222e8a7ad9cf1ccd22a', (string)$address2);

        $address3 = new WalletAddress('eth', '0xcfcecfe2bd2fed07a9145222e8a7ad9cf1ccd22a');
        $this->assertEquals(WalletAddress::NETWORK_ETH, $address3->getNetwork());
        $this->assertEquals('0xcfcecfe2bd2fed07a9145222e8a7ad9cf1ccd22a', $address3->getAddress());
        $this->assertEquals('eth:0xcfcecfe2bd2fed07a9145222e8a7ad9cf1ccd22a', (string)$address3);

        $address4 = new WalletAddress('btc', '3ALP7JRzHAyrhX5LLPSxU1A9duDiGbnaKg');
        $this->assertEquals(WalletAddress::NETWORK_BTC, $address4->getNetwork());
        $this->assertEquals('3ALP7JRzHAyrhX5LLPSxU1A9duDiGbnaKg', $address4->getAddress());
        $this->assertEquals('btc:3ALP7JRzHAyrhX5LLPSxU1A9duDiGbnaKg', (string)$address4);
    }

    public function testFromString(): void
    {
        $address1 = WalletAddress::fromString('ADS:0001-00000001-8B4E');
        $this->assertEquals(WalletAddress::NETWORK_ADS, $address1->getNetwork());
        $this->assertEquals('0001-00000001-8B4E', $address1->getAddress());
        $this->assertEquals('ads:0001-00000001-8B4E', (string)$address1);

        $address2 = WalletAddress::fromString('bsc:0xcfcecfe2bd2fed07a9145222e8a7ad9cf1ccd22a');
        $this->assertEquals(WalletAddress::NETWORK_BSC, $address2->getNetwork());
        $this->assertEquals('0xcfcecfe2bd2fed07a9145222e8a7ad9cf1ccd22a', $address2->getAddress());
        $this->assertEquals('bsc:0xcfcecfe2bd2fed07a9145222e8a7ad9cf1ccd22a', (string)$address2);

        $address3 = WalletAddress::fromString('Eth:0xcfcecfe2bd2fed07A9145222E8A7AD9CF1CCD22A');
        $this->assertEquals(WalletAddress::NETWORK_ETH, $address3->getNetwork());
        $this->assertEquals('0xcfcecfe2bd2fed07a9145222e8a7ad9cf1ccd22a', $address3->getAddress());
        $this->assertEquals('eth:0xcfcecfe2bd2fed07a9145222e8a7ad9cf1ccd22a', (string)$address3);

        $address4 = WalletAddress::fromString('btc:3ALP7JRzHAyrhX5LLPSxU1A9duDiGbnaKg');
        $this->assertEquals(WalletAddress::NETWORK_BTC, $address4->getNetwork());
        $this->assertEquals('3ALP7JRzHAyrhX5LLPSxU1A9duDiGbnaKg', $address4->getAddress());
        $this->assertEquals('btc:3ALP7JRzHAyrhX5LLPSxU1A9duDiGbnaKg', (string)$address4);
    }

    public function testInvalidNetwork(): void
    {
        $this->expectException(InvalidArgumentException::class);
        WalletAddress::fromString('foo:0001-00000001-8B4e');
    }

    public function testInvalidAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        WalletAddress::fromString('ads:0001-00000001');
    }

    public function walletAddresses(): array
    {
        return [
            ['', false],
            ['ads', false],
            ['foo', false],
            ['ads0001-00000001-8B4E', false],
            ['ads:0001-00000001-8B4E', true],
            ['ADS:0001-00000001-8B4E', true],
            ['ads:0001-00000001-8b4e', true],
            ['ads:0001-00000001-XXXX', true],
            ['ads:0001-00000001-1234', false],
            ['bsc:0xcfcecfe2bd2fed07a9145222e8a7ad9cf1ccd22a', true],
            ['BSC:0xcfcecfe2bd2fed07a9145222e8a7ad9cf1ccd22a', true],
            ['bsc:0xCFCECFE2BD2FED07A9145222E8A7AD9CF1CCD22A', true],
            ['bsc:cfcecfe2bd2fed07a9145222e8a7ad9cf1ccd22a', false],
            ['bsc:0xcfcecfe2bd2fed07a9145222e8a7ad9', false],
            ['bsc:0xcfcecfe2bd2fed07a9145222e8a7ad9cf1ccd22a123123', false],
            ['eth:0xcfcecfe2bd2fed07a9145222e8a7ad9cf1ccd22a', true],
            ['ETH:0xcfcecfe2bd2fed07a9145222e8a7ad9cf1ccd22a', true],
            ['eth:0xCFCECFE2BD2FED07A9145222E8A7AD9CF1CCD22A', true],
            ['eth:cfcecfe2bd2fed07a9145222e8a7ad9cf1ccd22a', false],
            ['eth:0xcfcecfe2bd2fed07a9145222e8a7ad9', false],
            ['eth:0xcfcecfe2bd2fed07a9145222e8a7ad9cf1ccd22a123123', false],
            ['btc:3ALP7JRzHAyrhX5LLPSxU1A9duDiGbnaKg', true],
            ['BTC:3ALP7JRzHAyrhX5LLPSxU1A9duDiGbnaKg', true],
            ['btc:30lP7JRzHAyrhX5LLPSxU1A9duDiGbnaKg', false],
            ['btc:3ALP7JRzHAy', false],
            ['btc:3ALP7JRzHAyrhX5LLPSxU1A9duDiGbnaKg3ALP7JRzHAyrhX5LLPSxU1A9duDiGbnaKg', false],
            ['foo:0001-00000001-8B4E', false],
        ];
    }

    /**
     * @dataProvider walletAddresses
     */
    public function testIsValid(string $address, bool $valid): void
    {
        $this->assertEquals($valid, WalletAddress::isValid($address), sprintf('Address: %s', $address));
    }
}
