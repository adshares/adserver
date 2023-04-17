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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\TurnoverEntry;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Supply\Domain\ValueObject\TurnoverEntryType;
use DateTimeImmutable;

class TurnoverEntryTest extends TestCase
{
    public function testIncreaseOrInsertWhileNotPresent(): void
    {
        TurnoverEntry::increaseOrInsert(
            new DateTimeImmutable('2023-04-17 13:00:00'),
            TurnoverEntryType::DspOperatorFee,
            123_456_789_000,
        );

        self::assertDatabaseCount(TurnoverEntry::class, 1);
        self::assertDatabaseHas(
            TurnoverEntry::class,
            [
                'hour_timestamp' => '2023-04-17 13:00:00',
                'type' => TurnoverEntryType::DspOperatorFee->name,
                'amount' => 123_456_789_000,
                'ads_address' => null,
            ],
        );
    }

    public function testIncreaseOrInsertWhilePresent(): void
    {
        TurnoverEntry::factory()->create(
            [
                'hour_timestamp' => '2023-04-17 13:00:00',
                'type' => TurnoverEntryType::DspOperatorFee->name,
                'amount' => 76_543_211_000,
            ]
        );

        TurnoverEntry::increaseOrInsert(
            new DateTimeImmutable('2023-04-17 13:00:00'),
            TurnoverEntryType::DspOperatorFee,
            123_456_789_000,
        );

        self::assertDatabaseCount(TurnoverEntry::class, 1);
        self::assertDatabaseHas(
            TurnoverEntry::class,
            [
                'hour_timestamp' => '2023-04-17 13:00:00',
                'type' => TurnoverEntryType::DspOperatorFee->name,
                'amount' => 200_000_000_000,
                'ads_address' => null,
            ],
        );
    }

    public function testIncreaseOrInsertWhilePresentDifferentType(): void
    {
        TurnoverEntry::factory()->create(
            [
                'hour_timestamp' => '2023-04-17 13:00:00',
                'type' => TurnoverEntryType::DspOperatorFee->name,
                'amount' => 76_543_211_000,
            ]
        );

        TurnoverEntry::increaseOrInsert(
            new DateTimeImmutable('2023-04-17 13:00:00'),
            TurnoverEntryType::DspLicenseFee,
            123_456_789_000,
        );

        self::assertDatabaseCount(TurnoverEntry::class, 2);
        self::assertDatabaseHas(
            TurnoverEntry::class,
            [
                'hour_timestamp' => '2023-04-17 13:00:00',
                'type' => TurnoverEntryType::DspOperatorFee->name,
                'amount' => 76_543_211_000,
                'ads_address' => null,
            ],
        );
        self::assertDatabaseHas(
            TurnoverEntry::class,
            [
                'hour_timestamp' => '2023-04-17 13:00:00',
                'type' => TurnoverEntryType::DspLicenseFee->name,
                'amount' => 123_456_789_000,
                'ads_address' => null,
            ],
        );
    }

    public function testIncreaseOrInsertWhileDifferentAddresses(): void
    {
        TurnoverEntry::increaseOrInsert(
            new DateTimeImmutable('2023-04-17 13:00:00'),
            TurnoverEntryType::DspExpense,
            76_543_211_000,
            '0001-00000000-9B6F',
        );
        TurnoverEntry::increaseOrInsert(
            new DateTimeImmutable('2023-04-17 13:00:00'),
            TurnoverEntryType::DspExpense,
            123_456_789_000,
            '0001-00000001-8B4E',
        );
        TurnoverEntry::increaseOrInsert(
            new DateTimeImmutable('2023-04-17 13:00:00'),
            TurnoverEntryType::DspExpense,
            111_000_000,
            '0001-00000001-8B4E',
        );

        self::assertDatabaseCount(TurnoverEntry::class, 2);
        self::assertDatabaseHas(
            TurnoverEntry::class,
            [
                'hour_timestamp' => '2023-04-17 13:00:00',
                'type' => TurnoverEntryType::DspExpense->name,
                'amount' => 76_543_211_000,
                'ads_address' => hex2bin('000100000000'),
            ],
        );
        self::assertDatabaseHas(
            TurnoverEntry::class,
            [
                'hour_timestamp' => '2023-04-17 13:00:00',
                'type' => TurnoverEntryType::DspExpense->name,
                'amount' => 123_567_789_000,
                'ads_address' => hex2bin('000100000001'),
            ],
        );
    }

    public function testFetchByHourTimestampSingleType(): void
    {
        TurnoverEntry::factory()
            ->count(4)
            ->sequence(
                [
                    'amount' => 1_000,
                    'hour_timestamp' => '2023-04-17 11:00:00',
                ],
                [
                    'amount' => 10_000,
                    'hour_timestamp' => '2023-04-17 12:00:00',
                ],
                [
                    'amount' => 100_000,
                    'hour_timestamp' => '2023-04-17 13:00:00',
                ],
                [
                    'amount' => 1_000_000,
                    'hour_timestamp' => '2023-04-17 14:00:00',
                ],
            )
            ->create(
                [
                    'type' => TurnoverEntryType::DspLicenseFee->name,
                ]
            );

        $result = TurnoverEntry::fetchByHourTimestamp(
            new DateTimeImmutable('2023-04-17 12:00:00'),
            new DateTimeImmutable('2023-04-17 14:00:00'),
        );

        self::assertCount(1, $result);
        self::assertEquals(1_110_000, $result[0]->amount);
        self::assertEquals(TurnoverEntryType::DspLicenseFee, $result[0]->type);
        self::assertNull($result[0]->ads_address);
    }

    public function testFetchByHourTimestampMultipleAddresses(): void
    {
        $expected = [
            '0001-00000000-9B6F' => 101_000,
            '0001-00000002-BB2D' => 1_010_000,
        ];
        TurnoverEntry::factory()
            ->count(5)
            ->sequence(
                [
                    'amount' => 100,
                    'hour_timestamp' => '2023-04-17 11:00:00',
                    'ads_address' => '0001-00000000-9B6F',
                ],
                [
                    'amount' => 1_000,
                    'hour_timestamp' => '2023-04-17 12:00:00',
                    'ads_address' => '0001-00000000-9B6F',
                ],
                [
                    'amount' => 10_000,
                    'hour_timestamp' => '2023-04-17 12:00:00',
                    'ads_address' => '0001-00000002-BB2D',
                ],
                [
                    'amount' => 100_000,
                    'hour_timestamp' => '2023-04-17 13:00:00',
                    'ads_address' => '0001-00000000-9B6F',
                ],
                [
                    'amount' => 1_000_000,
                    'hour_timestamp' => '2023-04-17 13:00:00',
                    'ads_address' => '0001-00000002-BB2D',
                ],
            )
            ->create(
                [
                    'type' => TurnoverEntryType::DspExpense->name,
                ]
            );

        $result = TurnoverEntry::fetchByHourTimestamp(
            new DateTimeImmutable('2023-04-17 12:00:00'),
            new DateTimeImmutable('2023-04-17 14:00:00'),
        );

        self::assertCount(2, $result);
        foreach ($result as $entry) {
            self::assertEquals(TurnoverEntryType::DspExpense, $entry->type);
            $address = $entry->ads_address;
            self::assertArrayHasKey($address, $expected);
            self::assertEquals($expected[$address], $entry->amount);
        }
    }
}
