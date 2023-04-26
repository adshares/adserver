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

namespace Adshares\Adserver\Tests\Utilities;

use Adshares\Adserver\Utilities\DateUtils;
use Adshares\Common\Domain\ValueObject\ChartResolution;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

final class DateUtilsTest extends TestCase
{
    /**
     * @dataProvider roundToNextHourProvider
     *
     * @param DateTime $dateInput
     * @param DateTime $dateOutputExpected
     */
    public function testGetDateTimeRoundedToNextHour(DateTime $dateInput, DateTime $dateOutputExpected): void
    {
        $dateOutput = DateUtils::getDateTimeRoundedToNextHour($dateInput);

        $this->assertEquals($dateOutputExpected, $dateOutput);
    }

    /**
     * @dataProvider roundToCurrentHourProvider
     *
     * @param DateTime $dateInput
     * @param DateTime $dateOutputExpected
     */
    public function testGetDateTimeRoundedToCurrentHour(DateTime $dateInput, DateTime $dateOutputExpected): void
    {
        $dateOutput = DateUtils::getDateTimeRoundedToCurrentHour($dateInput);

        $this->assertEquals($dateOutputExpected, $dateOutput);
    }

    /**
     * @dataProvider areTheSameHourProvider
     *
     * @param string $date1
     * @param string $date2
     * @param bool $areTheSameHour
     */
    public function testAreTheSameHour(string $date1, string $date2, bool $areTheSameHour): void
    {
        $this->assertEquals(
            $areTheSameHour,
            DateUtils::areTheSameHour(
                DateTime::createFromFormat(DateTimeInterface::ATOM, $date1),
                DateTime::createFromFormat(DateTimeInterface::ATOM, $date2)
            )
        );
    }

    /**
     * @dataProvider roundTimestampToHourProvider
     *
     * @param int $timestamp
     * @param int $expectedTimestamp
     */
    public function testRoundTimestampToHour(int $timestamp, int $expectedTimestamp): void
    {
        $this->assertEquals($expectedTimestamp, DateUtils::roundTimestampToHour($timestamp));
    }

    public function roundToNextHourProvider(): array
    {
        $table = [
            ['2019-01-26T09:21:56+0100', '2019-01-26T10:00:00+0100'],
            ['2019-01-26T23:21:56+0100', '2019-01-27T00:00:00+0100'],
            ['2019-01-26T09:00:00+0100', '2019-01-26T10:00:00+0100'],
        ];

        return $this->convertStringToDateTimeTable($table);
    }

    public function roundToCurrentHourProvider(): array
    {
        $table = [
            ['2019-01-26T09:21:56+0100', '2019-01-26T09:00:00+0100'],
            ['2019-01-26T09:00:00+0100', '2019-01-26T09:00:00+0100'],
        ];

        return $this->convertStringToDateTimeTable($table);
    }

    public function areTheSameHourProvider(): array
    {
        return [
            ['2019-01-26T09:21:56+0100', '2019-01-26T09:00:00+0100', true],
            ['2019-01-26T09:00:00+0100', '2019-01-26T09:00:00+0100', true],
            ['2019-01-26T09:21:56+0100', '2019-01-26T10:00:00+0200', true],
            ['2019-01-26T09:21:56+0100', '2019-01-26T10:00:00+0100', false],
            ['2019-01-26T09:00:00+0100', '2019-01-26T09:00:00+0300', false],
        ];
    }

    public function roundTimestampToHourProvider(): array
    {
        return [
            [1572012000, 1572012000],
            [1572012023, 1572012000],
        ];
    }

    private function convertStringToDateTimeTable(array $table): array
    {
        $convertedTable = [];
        foreach ($table as $row) {
            $convertedRow = [];
            foreach ($row as $item) {
                $convertedRow[] = DateTime::createFromFormat(DateTimeInterface::ATOM, $item);
            }
            $convertedTable[] = $convertedRow;
        }

        return $convertedTable;
    }

    /**
     * @dataProvider advanceDateTimeProvider
     */
    public function testAdvanceDateTime(string $expected, ChartResolution $resolution): void
    {
        $date = new DateTime('2019-01-26T09:00:00+0100');

        DateUtils::advanceStartDate($resolution, $date);

        self::assertEquals($expected, $date->format(DateTimeInterface::ATOM));
    }

    public function advanceDateTimeProvider(): array
    {
        return [
            'hour' => ['2019-01-26T10:00:00+01:00', ChartResolution::HOUR],
            'day' => ['2019-01-27T00:00:00+01:00', ChartResolution::DAY],
            'week' => ['2019-02-02T00:00:00+01:00', ChartResolution::WEEK],
            'month' => ['2019-02-01T00:00:00+01:00', ChartResolution::MONTH],
            'quarter' => ['2019-04-01T00:00:00+01:00', ChartResolution::QUARTER],
            'year' => ['2020-01-01T00:00:00+01:00', ChartResolution::YEAR],
        ];
    }

    /**
     * @dataProvider createSanitizedStartDateProvider
     */
    public function testCreateSanitizedStartDate(string $expected, ChartResolution $resolution): void
    {
        $date = new DateTime('2019-05-26T09:26:50+0100');
        $dateTimeZone = new DateTimeZone($date->format('O'));

        $startDate = DateUtils::createSanitizedStartDate($dateTimeZone, $resolution, $date);

        self::assertEquals($expected, $startDate->format(DateTimeInterface::ATOM));
    }

    public function createSanitizedStartDateProvider(): array
    {
        return [
            'hour' => ['2019-05-26T09:00:00+01:00', ChartResolution::HOUR],
            'day' => ['2019-05-26T00:00:00+01:00', ChartResolution::DAY],
            'week' => ['2019-05-20T00:00:00+01:00', ChartResolution::WEEK],
            'month' => ['2019-05-01T00:00:00+01:00', ChartResolution::MONTH],
            'quarter' => ['2019-04-01T00:00:00+01:00', ChartResolution::QUARTER],
            'year' => ['2019-01-01T00:00:00+01:00', ChartResolution::YEAR],
        ];
    }
}
