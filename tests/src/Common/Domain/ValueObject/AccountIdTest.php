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

use Adshares\Common\Domain\ValueObject\AccountId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AccountIdTest extends TestCase
{
    private const LOOSELY_VALID_STRINGS = [
        'ABCD-1234ABCD-XXXX',
        'ABCD-1234ABCD-xxxx',
        'ABCD-1234abcd-XXXX',
        'ABCD-1234abcd-xxxx',
        'abcd-1234ABCD-XXXX',
        'abcd-1234ABCD-xxxx',
        'abcd-1234abcd-XXXX',
        'abcd-1234abcd-xxxx',
    ];
    private const STRICTLY_VALID_STRINGS = [
        'ABCD-1234ABCD-27B9',
        'ABCD-1234ABCD-27b9',
        'ABCD-1234abcd-27B9',
        'ABCD-1234abcd-27b9',
        'abcd-1234ABCD-27B9',
        'abcd-1234ABCD-27b9',
        'abcd-1234abcd-27B9',
        'abcd-1234abcd-27b9',
    ];

    private const INVALID_STRINGS = [
        'b355-09cb45d6-9f75',
        '5CE9-50C64AB0-9445',
        'ADST-00000000-0000',
        '0000-00000000-adsx',
        'invalid string',
        '',
        '---',
        ' - - - ',
    ];

    private const INVALID_PAIRS = [
        'ADST-00000000',
        '0000-X000000X',
        'pair-invalidx',
        'invalid pair',
        'invalid-pair',
        '-',
        '',
    ];

    private const VALID_STRICT_PATTERN = '/^[0-9A-F]{4}-[0-9A-F]{8}-[0-9A-F]{4}$/i';
    private const VALID_LOOSE_PATTERN = '/^[0-9A-F]{4}-[0-9A-F]{8}-([0-9A-F]{4}|XXXX)$/i';

    /**
     * @test
     * @dataProvider validStringProvider
     *
     * @param string $string
     * @param bool $strict
     */
    public function createFromStringWithStrength(string $string, bool $strict): void
    {
        $id = new AccountId($string, $strict);

        self::assertSame(strtoupper($string), (string)$id);
    }

    public function validStringProvider(): array
    {
        $strictlyValidStrings = array_map(function (string $string) {
            return [$string, true];
        }, self::STRICTLY_VALID_STRINGS);
        $strictlyValidStrings2 = array_map(function (string $string) {
            return [$string, false];
        }, self::STRICTLY_VALID_STRINGS);
        $looselyValidStrings = array_map(function (string $string) {
            return [$string, false];
        }, self::LOOSELY_VALID_STRINGS);

        return array_merge($strictlyValidStrings, $strictlyValidStrings2, $looselyValidStrings);
    }

    /**
     * @test
     * @dataProvider invalidStringProvider
     *
     * @param string $string
     * @param bool $strict
     */
    public function failCreatingFromInvalidStringWithStrength(string $string, bool $strict): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AccountId($string, $strict);
    }

    public function invalidStringProvider(): array
    {
        $invalidStrings = array_map(function (string $string) {
            return [$string, true];
        }, self::INVALID_STRINGS);
        $invalidStrings2 = array_map(function (string $string) {
            return [$string, false];
        }, self::INVALID_STRINGS);

        $looselyValidStrings = array_map(function (string $string) {
            return [$string, true];
        }, self::LOOSELY_VALID_STRINGS);

        return array_merge($invalidStrings, $invalidStrings2, $looselyValidStrings);
    }

    /** @test */
    public function createFromIncompleteString(): void
    {
        self::assertSame('ABCD-1234ABCD-27B9', (string)AccountId::fromIncompleteString('ABCD-1234ABCD'));
        self::assertSame('ABCD-1234ABCD-27B9', (string)AccountId::fromIncompleteString('ABCD-1234ABCD', true));
        self::assertSame('ABCD-1234ABCD-XXXX', (string)AccountId::fromIncompleteString('ABCD-1234ABCD', false));
    }

    /**
     * @test
     * @dataProvider invalidPairProvider
     *
     * @param string $string
     * @param bool $strict
     */
    public function failCreatingFromInvalidPairWithStrength(string $string, bool $strict): void
    {
        $this->expectException(InvalidArgumentException::class);

        AccountId::fromIncompleteString($string, $strict);
    }

    public function invalidPairProvider(): array
    {
        $invalidPairs = array_map(function (string $string) {
            return [$string, true];
        }, self::INVALID_PAIRS);
        $invalidPairs2 = array_map(function (string $string) {
            return [$string, false];
        }, self::INVALID_PAIRS);

        return array_merge($invalidPairs, $invalidPairs2);
    }

    /** @test */
    public function createFromStringWithoutStrength(): void
    {
        self::assertSame('ABCD-1234ABCD-27B9', (string)new AccountId('ABCD-1234ABCD-27B9'));
    }

    /** @test */
    public function createRandom(): void
    {
        self::assertSame(1, preg_match(self::VALID_STRICT_PATTERN, (string)AccountId::random()));
        self::assertSame(1, preg_match(self::VALID_STRICT_PATTERN, (string)AccountId::random(true)));
        self::assertSame(1, preg_match(self::VALID_LOOSE_PATTERN, (string)AccountId::random(false)));
    }

    /** @test */
    public function equalityChecker(): void
    {
        $one = new AccountId('ABCD-1234ABCD-27B9');
        $one1 = new AccountId('ABCD-1234ABCD-27B9');

        self::assertTrue($one->equals($one1, true));
        self::assertTrue($one->equals($one1, false));
        self::assertTrue($one->equals($one1));

        $oneX = new AccountId('ABCD-1234ABCD-XXXX');

        self::assertFalse($one->equals($oneX, true));
        self::assertTrue($one->equals($oneX, false));
        self::assertTrue($one->equals($oneX));

        self::assertFalse($oneX->equals($one, true));
        self::assertTrue($oneX->equals($one, false));
        self::assertTrue($oneX->equals($one));

        $two = AccountId::fromIncompleteString('0000-1234ABCD', true);

        self::assertFalse($one->equals($two, true));
        self::assertFalse($one->equals($two, false));
        self::assertFalse($one->equals($two));

        self::assertFalse($two->equals($one, true));
        self::assertFalse($two->equals($one, false));
        self::assertFalse($two->equals($one));

        $twoX = AccountId::fromIncompleteString('0000-1234ABCD', false);

        self::assertFalse($one->equals($twoX, true));
        self::assertFalse($one->equals($twoX, false));
        self::assertFalse($one->equals($twoX));

        self::assertFalse($twoX->equals($one, true));
        self::assertFalse($twoX->equals($one, false));
        self::assertFalse($twoX->equals($one));
    }
}
