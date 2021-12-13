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

namespace Adshares\Tests\Common\Domain\ValueObject;

use Adshares\Common\Domain\ValueObject\WalletAddress;
use PHPUnit\Framework\TestCase;

class WalletAddressTest extends TestCase
{
    public function testConstruct(): void
    {
        $address = WalletAddress();
    }

    public function walletAddresses(): array
    {
        return [
            ['ads:0001-00000001-8B4E', true]
        ];
    }

    /**
     * @dataProvider walletAddresses
     */
    public function testIsValid(string $address, bool $valid): void
    {
        $this->assertEquals($valid, $address);
    }

    public function testFromSttring(): void
    {
        $address = WalletAddress::fromString('');
    }
}
