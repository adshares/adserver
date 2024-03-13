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
use Adshares\Common\Domain\ValueObject\ChartResolution;
use Adshares\Supply\Domain\ValueObject\TurnoverEntryType;
use DateTimeImmutable;

class TurnoverEntryTest extends TestCase
{
    private const EXPECTED_CHART_KEYS = [
        'date',
        'DspAdvertisersExpense',
        'DspLicenseFee',
        'DspOperatorFee',
        'DspCommunityFee',
        'DspExpense',
        'SspIncome',
        'SspLicenseFee',
        'SspOperatorFee',
        'SspPublishersIncome',
    ];

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

    public function testFetchByHourTimestampMultipleType(): void
    {
        $expected = [
            TurnoverEntryType::DspExpense->name => 1_100,
            TurnoverEntryType::SspIncome->name => 10_000,
        ];
        TurnoverEntry::factory()
            ->count(3)
            ->sequence(
                [
                    'ads_address' => '0001-00000000-9B6F',
                    'amount' => 100,
                    'hour_timestamp' => '2023-04-17 11:00:00',
                    'type' => TurnoverEntryType::DspExpense,
                ],
                [
                    'ads_address' => '0001-00000000-9B6F',
                    'amount' => 1_000,
                    'hour_timestamp' => '2023-04-17 12:00:00',
                    'type' => TurnoverEntryType::DspExpense,
                ],
                [
                    'ads_address' => '0001-00000002-BB2D',
                    'amount' => 10_000,
                    'hour_timestamp' => '2023-04-17 12:00:00',
                    'type' => TurnoverEntryType::SspIncome,
                ],
            )
            ->create();

        $result = TurnoverEntry::fetchByHourTimestamp(
            new DateTimeImmutable('2023-04-17 11:00:00'),
            new DateTimeImmutable('2023-04-17 12:00:00'),
        );

        self::assertCount(2, $result);
        foreach ($result as $entry) {
            $type = $entry->type->name;
            self::assertArrayHasKey($type, $expected);
            self::assertEquals($expected[$type], $entry->amount);
        }
    }

    public function testFetchByHourTimestampForChartHourly(): void
    {
        TurnoverEntry::factory()
            ->count(7)
            ->sequence(
                [
                    'ads_address' => '0001-00000000-9B6F',
                    'amount' => 100,
                    'hour_timestamp' => '2023-04-17 11:00:00',
                    'type' => TurnoverEntryType::SspIncome,
                ],
                [
                    'amount' => 10,
                    'hour_timestamp' => '2023-04-17 11:00:00',
                    'type' => TurnoverEntryType::SspPublishersIncome,
                ],
                [
                    'ads_address' => '0001-00000000-9B6F',
                    'amount' => 100,
                    'hour_timestamp' => '2023-04-17 12:00:00',
                    'type' => TurnoverEntryType::SspIncome,
                ],
                [
                    'ads_address' => '0001-00000002-BB2D',
                    'amount' => 1_000,
                    'hour_timestamp' => '2023-04-17 12:00:00',
                    'type' => TurnoverEntryType::SspIncome,
                ],
                [
                    'ads_address' => '0001-00000024-9B6F',
                    'amount' => 11,
                    'hour_timestamp' => '2023-04-17 12:00:00',
                    'type' => TurnoverEntryType::SspLicenseFee,
                ],
                [
                    'amount' => 359,
                    'hour_timestamp' => '2023-04-17 12:00:00',
                    'type' => TurnoverEntryType::SspOperatorFee,
                ],
                [
                    'amount' => 730,
                    'hour_timestamp' => '2023-04-17 12:00:00',
                    'type' => TurnoverEntryType::SspPublishersIncome,
                ],
            )
            ->create();
        $result = TurnoverEntry::fetchByHourTimestampForChart(
            new DateTimeImmutable('2023-04-17 10:00:00'),
            new DateTimeImmutable('2023-04-17 12:00:00'),
            ChartResolution::HOUR,
        );

        self::assertCount(3, $result);

        foreach ($result as $entry) {
            foreach (self::EXPECTED_CHART_KEYS as $expectedKey) {
                self::assertArrayHasKey($expectedKey, $entry);
            }
        }
        self::assertEquals(0, $result[0][TurnoverEntryType::SspIncome->name]);
        self::assertEquals(100, $result[1][TurnoverEntryType::SspIncome->name]);
        self::assertEquals(1100, $result[2][TurnoverEntryType::SspIncome->name]);
    }

    public function testFetchByHourTimestampForChartHourlyWithTimezone(): void
    {
        TurnoverEntry::factory()
            ->count(3)->sequence(
                [
                    'amount' => 10,
                    'hour_timestamp' => '2023-04-10 10:00:00',
                ],
                [
                    'amount' => 200,
                    'hour_timestamp' => '2023-04-10 11:00:00',
                ],
                [
                    'amount' => 3000,
                    'hour_timestamp' => '2023-04-10 12:00:00',
                ],
            )
            ->create(
                [
                    'ads_address' => '0001-00000000-9B6F',
                    'type' => TurnoverEntryType::SspIncome,
                ]
            );

        $result = TurnoverEntry::fetchByHourTimestampForChart(
            new DateTimeImmutable('2023-04-10T10:00:00+01:00'),
            new DateTimeImmutable('2023-04-10T12:00:00+01:00'),
            ChartResolution::HOUR,
        );

        self::assertCount(3, $result);

        foreach ($result as $entry) {
            foreach (self::EXPECTED_CHART_KEYS as $expectedKey) {
                self::assertArrayHasKey($expectedKey, $entry);
            }
        }
        self::assertEquals(0, $result[0][TurnoverEntryType::SspIncome->name]);
        self::assertEquals(10, $result[1][TurnoverEntryType::SspIncome->name]);
        self::assertEquals(200, $result[2][TurnoverEntryType::SspIncome->name]);
    }

    public function testFetchByHourTimestampForChartDaily(): void
    {
        TurnoverEntry::factory()
            ->count(3)
            ->sequence(
                [
                    'ads_address' => '0001-00000000-9B6F',
                    'amount' => 100,
                    'hour_timestamp' => '2023-04-13 11:00:00',
                    'type' => TurnoverEntryType::SspIncome,
                ],
                [
                    'ads_address' => '0001-00000000-9B6F',
                    'amount' => 100,
                    'hour_timestamp' => '2023-04-14 12:00:00',
                    'type' => TurnoverEntryType::SspIncome,
                ],
                [
                    'ads_address' => '0001-00000002-BB2D',
                    'amount' => 1_000,
                    'hour_timestamp' => '2023-04-14 20:00:00',
                    'type' => TurnoverEntryType::SspIncome,
                ],
            )
            ->create();
        $result = TurnoverEntry::fetchByHourTimestampForChart(
            new DateTimeImmutable('2023-04-12 00:00:00'),
            new DateTimeImmutable('2023-04-14 23:59:59'),
            ChartResolution::DAY,
        );

        self::assertCount(3, $result);

        foreach ($result as $entry) {
            foreach (self::EXPECTED_CHART_KEYS as $expectedKey) {
                self::assertArrayHasKey($expectedKey, $entry);
            }
        }
        self::assertEquals(0, $result[0][TurnoverEntryType::SspIncome->name]);
        self::assertEquals(100, $result[1][TurnoverEntryType::SspIncome->name]);
        self::assertEquals(1100, $result[2][TurnoverEntryType::SspIncome->name]);
    }

    public function testFetchByHourTimestampForChartMonthly(): void
    {
        TurnoverEntry::factory()
            ->count(3)
            ->sequence(
                [
                    'ads_address' => '0001-00000000-9B6F',
                    'amount' => 100,
                    'hour_timestamp' => '2023-03-13 11:00:00',
                    'type' => TurnoverEntryType::SspIncome,
                ],
                [
                    'ads_address' => '0001-00000000-9B6F',
                    'amount' => 100,
                    'hour_timestamp' => '2023-04-01 12:00:00',
                    'type' => TurnoverEntryType::SspIncome,
                ],
                [
                    'ads_address' => '0001-00000002-BB2D',
                    'amount' => 1_000,
                    'hour_timestamp' => '2023-04-10 20:00:00',
                    'type' => TurnoverEntryType::SspIncome,
                ],
            )
            ->create();
        $result = TurnoverEntry::fetchByHourTimestampForChart(
            new DateTimeImmutable('2023-02-12 00:00:00'),
            new DateTimeImmutable('2023-04-11 23:59:59'),
            ChartResolution::MONTH,
        );

        self::assertCount(3, $result);

        foreach ($result as $entry) {
            foreach (self::EXPECTED_CHART_KEYS as $expectedKey) {
                self::assertArrayHasKey($expectedKey, $entry);
            }
        }
        self::assertEquals('2023-02-12T00:00:00+00:00', $result[0]['date']);
        self::assertEquals(0, $result[0][TurnoverEntryType::SspIncome->name]);
        self::assertEquals(100, $result[1][TurnoverEntryType::SspIncome->name]);
        self::assertEquals(1100, $result[2][TurnoverEntryType::SspIncome->name]);
    }

    public function testFetchByHourTimestampForChartWeekly(): void
    {
        TurnoverEntry::factory()
            ->count(3)
            ->sequence(
                [
                    'ads_address' => '0001-00000000-9B6F',
                    'amount' => 100,
                    'hour_timestamp' => '2023-04-14 11:00:00',
                    'type' => TurnoverEntryType::SspIncome,
                ],
                [
                    'ads_address' => '0001-00000000-9B6F',
                    'amount' => 100,
                    'hour_timestamp' => '2023-04-18 12:00:00',
                    'type' => TurnoverEntryType::SspIncome,
                ],
                [
                    'ads_address' => '0001-00000002-BB2D',
                    'amount' => 1_000,
                    'hour_timestamp' => '2023-04-20 20:00:00',
                    'type' => TurnoverEntryType::SspIncome,
                ],
            )
            ->create();
        $result = TurnoverEntry::fetchByHourTimestampForChart(
            new DateTimeImmutable('2023-04-12 00:00:00'),
            new DateTimeImmutable('2023-04-22 23:59:59'),
            ChartResolution::WEEK,
        );

        self::assertCount(2, $result);

        foreach ($result as $entry) {
            foreach (self::EXPECTED_CHART_KEYS as $expectedKey) {
                self::assertArrayHasKey($expectedKey, $entry);
            }
        }
        self::assertEquals('2023-04-12T00:00:00+00:00', $result[0]['date']);
        self::assertEquals(100, $result[0][TurnoverEntryType::SspIncome->name]);
        self::assertEquals(1100, $result[1][TurnoverEntryType::SspIncome->name]);
    }

    public function testFetchByHourTimestampForChartWhileEmpty(): void
    {

        $result = TurnoverEntry::fetchByHourTimestampForChart(
            new DateTimeImmutable('2023-04-17 10:00:00'),
            new DateTimeImmutable('2023-04-17 12:00:00'),
            ChartResolution::HOUR,
        );

        self::assertCount(3, $result);

        foreach ($result as $entry) {
            foreach (self::EXPECTED_CHART_KEYS as $expectedKey) {
                self::assertArrayHasKey($expectedKey, $entry);
            }
        }
        self::assertEquals(0, $result[0][TurnoverEntryType::SspIncome->name]);
        self::assertEquals(0, $result[1][TurnoverEntryType::SspIncome->name]);
        self::assertEquals(0, $result[2][TurnoverEntryType::SspIncome->name]);
    }

    public function testFetchByHourTimestampAndType(): void
    {
        $expected = [
            '0001-00000000-9B6F' => 101_000,
            '0001-00000002-BB2D' => 1_010_000,
        ];
        TurnoverEntry::factory()
            ->count(6)
            ->sequence(
                [
                    'ads_address' => '0001-00000000-9B6F',
                    'amount' => 100,
                    'hour_timestamp' => '2023-04-17 11:00:00',
                    'type' => TurnoverEntryType::DspExpense,
                ],
                [
                    'ads_address' => '0001-00000000-9B6F',
                    'amount' => 1_000,
                    'hour_timestamp' => '2023-04-17 12:00:00',
                    'type' => TurnoverEntryType::DspExpense,
                ],
                [
                    'ads_address' => '0001-00000002-BB2D',
                    'amount' => 10_000,
                    'hour_timestamp' => '2023-04-17 12:00:00',
                    'type' => TurnoverEntryType::DspExpense,
                ],
                [
                    'ads_address' => '0001-00000000-9B6F',
                    'amount' => 100_000,
                    'hour_timestamp' => '2023-04-17 13:00:00',
                    'type' => TurnoverEntryType::DspExpense,
                ],
                [
                    'ads_address' => '0001-00000002-BB2D',
                    'amount' => 1_000_000,
                    'hour_timestamp' => '2023-04-17 13:00:00',
                    'type' => TurnoverEntryType::DspExpense,
                ],
                [
                    'ads_address' => '0001-00000002-BB2D',
                    'amount' => 1_000_000,
                    'hour_timestamp' => '2023-04-17 13:00:00',
                    'type' => TurnoverEntryType::SspIncome,
                ],
            )
            ->create();

        $result = TurnoverEntry::fetchByHourTimestampAndType(
            new DateTimeImmutable('2023-04-17 12:00:00'),
            new DateTimeImmutable('2023-04-17 14:00:00'),
            TurnoverEntryType::DspExpense,
        );

        self::assertCount(2, $result);
        foreach ($result as $entry) {
            $address = $entry->ads_address;
            self::assertArrayHasKey($address, $expected);
            self::assertEquals($expected[$address], $entry->amount);
        }
    }
}
