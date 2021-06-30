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

use Adshares\Adserver\Utilities\BinaryStringUtils;
use Closure;
use PHPUnit\Framework\TestCase;

final class BinaryStringUtilsTest extends TestCase
{
    /**
     * @dataProvider countSetBitsProvider
     *
     * @param string $binaryString
     * @param int $expectedBitsCount
     */
    public function testCountSetBits(string $binaryString, int $expectedBitsCount): void
    {
        $this->assertEquals($expectedBitsCount, BinaryStringUtils::count($binaryString));
    }

    /**
     * @dataProvider andStringProvider
     *
     * @param string $binaryStringA
     * @param string $binaryStringB
     * @param string $expectedBinaryString
     */
    public function testAndString(string $binaryStringA, string $binaryStringB, string $expectedBinaryString): void
    {
        $this->assertEquals($expectedBinaryString, BinaryStringUtils::and($binaryStringA, $binaryStringB));
    }

    /**
     * @dataProvider notStringProvider
     *
     * @param string $binaryString
     * @param string $expectedBinaryString
     */
    public function testNotString(string $binaryString, string $expectedBinaryString): void
    {
        $this->assertEquals($expectedBinaryString, BinaryStringUtils::not($binaryString));
    }

    /**
     * @dataProvider orStringProvider
     *
     * @param string $binaryStringA
     * @param string $binaryStringB
     * @param string $expectedBinaryString
     */
    public function testOrString(string $binaryStringA, string $binaryStringB, string $expectedBinaryString): void
    {
        $this->assertEquals($expectedBinaryString, BinaryStringUtils::or($binaryStringA, $binaryStringB));
    }

    public function countSetBitsProvider(): array
    {
        return array_map(
            function ($item) {
                return [pack('H*', base_convert($item[0], 2, 16)), $item[1]];
            },
            [
                '4 bits' => ['0100', 1],
                '16 bits' => ['0100100000000101', 4],
                '32 bits' => ['01001000000001010100100000000101', 8],
                '64 bits' => ['0100100000110101010010000000010101001000101001010100100000000101', 20],
            ]
        );
    }

    public function andStringProvider(): array
    {
        return array_map(
            $this->convertToBinary(),
            [
                '4 bits' => [
                    '0100',
                    '0001',
                    '0000',
                ],
                '16 bits' => [
                    '0100100001000101',
                    '0010010001001110',
                    '0000000001000100',
                ],
                '32 bits' => [
                    '01011000000001010100100000000101',
                    '00010010001111101001000000001001',
                    '00010000000001000000000000000001',
                ],
                '64 bits' => [
                    '0100100000110101010010000000010101001000101001010100100000000101',
                    '1110000101100101011010110001000010101101001010011101001100110000',
                    '0100000000100101010010000000000000001000001000010100000000000000',
                ],
                '128 bits' => [
                    '1100100000110101010100000000010101001000101001010110100000000101'
                    . '0100100000110101010010000000010101001000101001010100100000000101',
                    '1110000101100101011010110001000010101101001010010001001100010000'
                    . '1110000101100101011010110001000010101101001010011101001100110000',
                    '1100000000100101010000000000000000001000001000010000000000000000'
                    . '0100000000100101010010000000000000001000001000010100000000000000',
                ],
            ]
        );
    }

    public function notStringProvider(): array
    {
        return array_map(
            $this->convertToBinary(),
            [
                '8 bits' => [
                    '01000100',
                    '10111011',
                ],
                '16 bits' => [
                    '0100100000000101',
                    '1011011111111010',
                ],
                '32 bits' => [
                    '01001000000001010100100000000101',
                    '10110111111110101011011111111010',
                ],
                '64 bits' => [
                    '0100100000110101010010000000010101001000101001010100100000000101',
                    '1011011111001010101101111111101010110111010110101011011111111010',
                ],
                '128 bits' => [
                    '0100100000110101010100000000010101001000101001010110100000000101'
                    . '0100100000110101010010000000010101001000101001010100100000000101',
                    '1011011111001010101011111111101010110111010110101001011111111010'
                    . '1011011111001010101101111111101010110111010110101011011111111010',
                ],
            ]
        );
    }

    public function orStringProvider(): array
    {
        return array_map(
            $this->convertToBinary(),
            [
                '4 bits' => [
                    '0100',
                    '0001',
                    '0101',
                ],
                '16 bits' => [
                    '0100100000000101',
                    '0010010001001010',
                    '0110110001001111',
                ],
                '32 bits' => [
                    '01001000000001010100100000000101',
                    '00010010001111101001000000001001',
                    '01011010001111111101100000001101',
                ],
                '64 bits' => [
                    '0100100000110101010010000000010101001000101001010100100000000101',
                    '1110000101100101011010110001000010101101001010011101001100110000',
                    '1110100101110101011010110001010111101101101011011101101100110101',
                ],
                '128 bits' => [
                    '0100100000110101010100000000010101001000101001010110100000000101'
                    . '0100100000110101010010000000010101001000101001010100100000000101',
                    '1110000101100101011010110001000010101101001010010001001100010000'
                    . '1110000101100101011010110001000010101101001010011101001100110000',
                    '1110100101110101011110110001010111101101101011010111101100010101'
                    . '1110100101110101011010110001010111101101101011011101101100110101',
                ],
            ]
        );
    }

    private function convertToBinary(): Closure
    {
        return function ($item) {
            return array_map(
                function ($element) {
                    $baseConvert = join(
                        array_map(
                            function ($item) {
                                $input = base_convert($item, 2, 16);

                                return str_pad($input, strlen($item) / 4, '0', STR_PAD_LEFT);
                            },
                            str_split($element, 32)
                        )
                    );

                    return pack('H*', $baseConvert);
                },
                $item
            );
        };
    }
}
