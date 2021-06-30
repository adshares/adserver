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

declare(strict_types=1);

namespace Adshares\Adserver\Tests\Utilities;

use Adshares\Adserver\Utilities\AdsUtils;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AdsUtilsTest extends TestCase
{
    /**
     * @test
     * @dataProvider calculateAmountProvider
     *
     * @param string $addrFrom
     * @param string $addrTo
     * @param int $total
     * @param int $expectedAmount
     */
    public function calculateAmount(string $addrFrom, string $addrTo, int $total, int $expectedAmount): void
    {
        $amount = AdsUtils::calculateAmount($addrFrom, $addrTo, $total);

        $this->assertEquals($expectedAmount, $amount);
    }

    public function calculateAmountProvider(): array
    {
        return [
            // same node
            ['0001-00000000-XXXX', '0001-00000000-XXXX', AdsUtils::TXS_MIN_FEE, 0],
            ['0001-00000000-XXXX', '0001-00000000-XXXX', 10001, 1],
            ['0001-00000000-XXXX', '0002-00000000-XXXX', 10009999, 9999999],
            ['0001-00000000-XXXX', '0001-00000000-XXXX', 1500750000000, 1500000000000],
            ['0001-00000000-XXXX', '0001-00000000-XXXX', 1801000050000, 1800100000000],
            ['0001-00000000-XXXX', '0001-00000000-XXXX', 1235117250000000, 1234500000000000],
            ['0001-00000000-XXXX', '0001-00000000-XXXX', 1235117250001999, 1234500000001999],
            // different nodes
            ['0001-00000000-XXXX', '0002-00000000-XXXX', AdsUtils::TXS_MIN_FEE, 0],
            ['0001-00000000-XXXX', '0002-00000000-XXXX', 10001, 1],
            ['0001-00000000-XXXX', '0002-00000000-XXXX', 10009999, 9999999],
            ['0001-00000000-XXXX', '0002-00000000-XXXX', 1501500000000, 1500000000000],
            ['0001-00000000-XXXX', '0002-00000000-XXXX', 1801900100000, 1800100000000],
            ['0001-00000000-XXXX', '0002-00000000-XXXX', 1235734500001999, 1234500000001999],
        ];
    }

    /**
     * @test
     * @dataProvider calculateFeeProvider
     *
     * @param string $addrFrom
     * @param string $addrTo
     * @param int $amount
     * @param int $expectedFee
     */
    public function calculateFee(string $addrFrom, string $addrTo, int $amount, int $expectedFee): void
    {
        $fee = AdsUtils::calculateFee($addrFrom, $addrTo, $amount);

        $this->assertEquals($expectedFee, $fee);
    }

    public function calculateFeeProvider(): array
    {
        return [
            // same node
            ['0001-00000000-XXXX', '0001-00000000-XXXX', 0, AdsUtils::TXS_MIN_FEE],
            ['0001-00000000-XXXX', '0001-00000000-XXXX', AdsUtils::TXS_MIN_FEE, AdsUtils::TXS_MIN_FEE],
            ['0001-00000000-XXXX', '0001-00000000-XXXX', 100000000000, 50000000],
            ['0001-00000000-XXXX', '0001-00000000-XXXX', 1505109997183369437, 752554998591684],
            ['0001-00000000-XXXX', '0001-00000000-XXXX', 1719698664242409873, 859849332121204],
            // different nodes
            ['0001-00000000-XXXX', '0002-00000000-XXXX', 0, AdsUtils::TXS_MIN_FEE],
            ['0001-00000000-XXXX', '0002-00000000-XXXX', AdsUtils::TXS_MIN_FEE, AdsUtils::TXS_MIN_FEE],
            ['0001-00000000-XXXX', '0002-00000000-XXXX', 100000000000, 100000000],
            ['0001-00000000-XXXX', '0002-00000000-XXXX', 860402936314359351, 860402936314358],
            ['0001-00000000-XXXX', '0002-00000000-XXXX', 1505185149556105113, 1505185149556104],
        ];
    }

    public function testEncodeTxId(): void
    {
        $input = '00010000F01F0001';
        $expected = '0001:0000F01F:0001';

        self::assertEquals($expected, AdsUtils::encodeTxId($input));
    }

    public function testDecodeTxId(): void
    {
        $input = '0001:0000F01F:0001';
        $expected = '00010000F01F0001';

        self::assertEquals($expected, AdsUtils::decodeTxId($input));
    }

    public function testDecodeTxIdInvalid(): void
    {
        $input = 'invalid';

        self::assertNull(AdsUtils::decodeTxId($input));
    }

    public function testDecodeAddress(): void
    {
        $input = '0001-00000028-3E05';
        $expected = '000100000028';

        self::assertEquals($expected, AdsUtils::decodeAddress($input));
    }

    public function testDecodeAddressInvalid(): void
    {
        $input = 'invalid';

        self::assertNull(AdsUtils::decodeAddress($input));
    }

    public function testNormalizeAddress(): void
    {
        $input = '0001000000283E05';
        $expected = '0001-00000028-3E05';

        self::assertEquals($expected, AdsUtils::normalizeAddress($input));
    }

    public function testNormalizeAddressInvalid(): void
    {
        $input = 'invalid';

        $this->expectException(RuntimeException::class);

        AdsUtils::normalizeAddress($input);
    }

    public function testNormalizeTxid(): void
    {
        $input = '00010000F01F0001';
        $expected = '0001:0000F01F:0001';

        self::assertEquals($expected, AdsUtils::normalizeTxid($input));
    }

    public function testNormalizeTxidInvalid(): void
    {
        $input = 'invalid';

        $this->expectException(RuntimeException::class);

        AdsUtils::normalizeTxid($input);
    }
}
