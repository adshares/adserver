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

namespace Adshares\Test\Common\Domain\ValueObject;

use Adshares\Common\Domain\ValueObject\TransactionId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TransactionIdTest extends TestCase
{
    private const VALID_STRINGS = [
        'ABCD:1234ABCD:27B9',
        'ABCD:1234ABCD:27b9',
        'ABCD:1234abcd:27B9',
        'ABCD:1234abcd:27b9',
        'abcd:1234ABCD:27B9',
        'abcd:1234ABCD:27b9',
        'abcd:1234abcd:27B9',
        'abcd:1234abcd:27b9',
    ];

    private const INVALID_STRINGS = [
        'ADST:00000000:0000',
        '0000:00000000:adsx',
        'b355:09cb45d6:XXXX',
        '5CE9:50C64AB0:XXXX',
        'ADST:00000000:XXXX',
        '0000:00000000:XXXX',
        'invalid string',
        '',
        ':::',
        ' : : : ',
    ];

    private const VALID_PATTERN = '/^[0-9A-F]{4}:[0-9A-F]{8}:[0-9A-F]{4}$/i';

    /**
     * @test
     * @dataProvider validStringProvider
     *
     * @param string $string
     * \     */
    public function createFromString(string $string): void
    {
        $id = new TransactionId($string);

        self::assertSame(strtoupper($string), (string)$id);
    }

    public function validStringProvider(): array
    {
        return array_map(function (string $string) {
            return [$string];
        }, self::VALID_STRINGS);
    }

    /**
     * @test
     * @dataProvider invalidStringProvider
     *
     * @param string $string
     */
    public function failCreatingFromInvalidString(string $string): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TransactionId($string);
    }

    public function invalidStringProvider(): array
    {
        return array_map(function (string $string) {
            return [$string];
        }, self::INVALID_STRINGS);
    }

    /** @test */
    public function createRandom(): void
    {
        self::assertSame(1, preg_match(self::VALID_PATTERN, (string)TransactionId::random()));
    }

    /** @test */
    public function equalityChecker(): void
    {
        $one = new TransactionId('ABCD:EF123456:7890');
        $one1 = new TransactionId('ABCD:EF123456:7890');

        self::assertTrue($one->equals($one1));
        self::assertTrue($one1->equals($one));

        $two = new TransactionId('0000:00000000:0000');

        self::assertFalse($one->equals($two));
        self::assertFalse($two->equals($one));
    }
}
