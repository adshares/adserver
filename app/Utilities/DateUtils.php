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

namespace Adshares\Adserver\Utilities;

use Adshares\Common\Domain\ValueObject\ChartResolution;
use DateTime;
use DateTimeZone;

final class DateUtils
{
    public const HOUR = 3600;

    public static function getDateTimeRoundedToNextHour(DateTime $date = null): DateTime
    {
        $roundedDate = (null === $date) ? new DateTime() : clone $date;
        $roundedDate->setTime(1 + (int)$roundedDate->format("H"), 0, 0);

        return $roundedDate;
    }

    public static function getDateTimeRoundedToCurrentHour(DateTime $date = null): DateTime
    {
        $roundedDate = (null === $date) ? new DateTime() : clone $date;
        $roundedDate->setTime((int)$roundedDate->format("H"), 0, 0);

        return $roundedDate;
    }

    public static function areTheSameHour(DateTime $dateTimeInput1, DateTime $dateTimeInput2): bool
    {
        $dateTime1 = clone $dateTimeInput1;
        $dateTime2 = clone $dateTimeInput2;

        $dateTime1->setTime((int)$dateTime1->format('H'), 0);
        $dateTime2->setTime((int)$dateTime2->format('H'), 0);

        return $dateTime1 == $dateTime2;
    }

    public static function roundTimestampToHour(int $timestamp): int
    {
        return ((int)floor($timestamp / self::HOUR)) * self::HOUR;
    }

    public static function advanceStartDate(ChartResolution $resolution, DateTime $date): void
    {
        switch ($resolution) {
            case ChartResolution::HOUR:
                $date->modify('next hour');
                break;
            case ChartResolution::DAY:
                $date->modify('tomorrow');
                break;
            case ChartResolution::WEEK:
                $date->modify('+7 days midnight');
                break;
            case ChartResolution::MONTH:
                $date->modify('first day of next month midnight');
                break;
            case ChartResolution::QUARTER:
                $date->modify('first day of third month midnight');
                break;
            default:
                $date->modify('first day of January next year midnight');
                break;
        }
    }

    public static function createSanitizedStartDate(
        DateTimeZone $dateTimeZone,
        ChartResolution $resolution,
        DateTime $dateStart,
    ): DateTime {
        $date = (clone $dateStart)->setTimezone($dateTimeZone);

        switch ($resolution) {
            case ChartResolution::HOUR:
                $date->setTime((int)$date->format('H'), 0);
                break;
            case ChartResolution::DAY:
                $date->modify('midnight');
                break;
            case ChartResolution::WEEK:
                $date->modify('Monday this week midnight');
                break;
            case ChartResolution::MONTH:
                $date->modify('first day of this month midnight');
                break;
            case ChartResolution::QUARTER:
                $quarter = (int)floor(((int)$date->format('m') - 1) / 3);
                $month = $quarter * 3 + 1;
                $date->setDate((int)$date->format('Y'), $month, 1)
                    ->modify('midnight');
                break;
            case ChartResolution::YEAR:
                $date->modify('first day of January this year midnight');
                break;
        }

        return $date;
    }
}
