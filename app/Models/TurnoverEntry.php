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

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Models\Traits\AccountAddress;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Adserver\Utilities\DateUtils;
use Adshares\Adserver\Utilities\SqlUtils;
use Adshares\Common\Domain\ValueObject\ChartResolution;
use Adshares\Supply\Domain\ValueObject\TurnoverEntryType;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @mixin Builder
 * @property int id
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property DateTimeInterface hour_timestamp
 * @property TurnoverEntryType type
 * @property int amount
 * @property string|null ads_address
 */
class TurnoverEntry extends Model
{
    use AccountAddress;
    use AutomateMutators;
    use HasFactory;

    private const AMOUNT_BY_TYPE_COLUMNS = [
        'SUM(IF(type = "DspAdvertisersExpense", amount, 0)) AS DspAdvertisersExpense',
        'SUM(IF(type = "DspLicenseFee", amount, 0)) AS DspLicenseFee',
        'SUM(IF(type = "DspOperatorFee", amount, 0)) AS DspOperatorFee',
        'SUM(IF(type = "DspCommunityFee", amount, 0)) AS DspCommunityFee',
        'SUM(IF(type = "DspExpense", amount, 0)) AS DspExpense',
        'SUM(IF(type = "SspIncome", amount, 0)) AS SspIncome',
        'SUM(IF(type = "SspLicenseFee", amount, 0)) AS SspLicenseFee',
        'SUM(IF(type = "SspOperatorFee", amount, 0)) AS SspOperatorFee',
        'SUM(IF(type = "SspPublishersIncome", amount, 0)) AS SspPublishersIncome',
    ];

    protected $casts = [
        'type' => TurnoverEntryType::class,
    ];

    protected $traitAutomate = [
        'ads_address' => 'AccountAddress',
    ];

    public static function increaseOrInsert(
        DateTimeInterface $hourTimestamp,
        TurnoverEntryType $type,
        int $amount,
        ?string $adsAddress = null,
    ): void {
        /** @var ?self $entry */
        $query = self::query()
            ->where('hour_timestamp', $hourTimestamp)
            ->where('type', $type->name);
        if (null === $adsAddress) {
            $query->whereNull('ads_address');
        } else {
            $query->where('ads_address', hex2bin(AdsUtils::decodeAddress($adsAddress)));
        }
        $entry = $query->first();

        if (null === $entry) {
            $entry = new self();
            $entry->hour_timestamp = $hourTimestamp;
            $entry->type = $type;
            $entry->amount = $amount;
            $entry->ads_address = $adsAddress;
        } else {
            $entry->amount += $amount;
        }

        $entry->save();
    }

    public static function fetchByHourTimestamp(DateTimeInterface $from, DateTimeInterface $to): Collection
    {
        $dateTimeZone = new DateTimeZone($from->format('O'));
        $closure = fn() => self::query()
            ->where('hour_timestamp', '>=', $from)
            ->where('hour_timestamp', '<=', $to)
            ->selectRaw('SUM(amount) as amount, type')
            ->groupBy('type')
            ->get();
        return SqlUtils::executeTimezoneAwareQuery($dateTimeZone, $closure);
    }

    public static function fetchByHourTimestampAndType(
        DateTimeInterface $from,
        DateTimeInterface $to,
        TurnoverEntryType $type,
    ): Collection {
        $dateTimeZone = new DateTimeZone($from->format('O'));
        $closure = fn() => self::query()
            ->where('hour_timestamp', '>=', $from)
            ->where('hour_timestamp', '<=', $to)
            ->where('type', $type->name)
            ->selectRaw('SUM(amount) as amount, ads_address')
            ->groupBy('ads_address')
            ->get();
        return SqlUtils::executeTimezoneAwareQuery($dateTimeZone, $closure);
    }

    public static function fetchByHourTimestampForChart(
        DateTimeInterface $from,
        DateTimeInterface $to,
        ChartResolution $resolution,
    ): array {
        $builder = self::query()
            ->where('hour_timestamp', '>=', $from)
            ->where('hour_timestamp', '<=', $to);

        $dateColumn = match ($resolution) {
            ChartResolution::HOUR => 'hour_timestamp AS date',
            ChartResolution::DAY =>
                'CONCAT('
                . 'YEAR(hour_timestamp),'
                . '"-",'
                . 'LPAD(MONTH(hour_timestamp),2,"0"),'
                . '"-",'
                . 'LPAD(DAY(hour_timestamp),2,"0"),'
                . '" 00:00:00"'
                . ') AS date',
            ChartResolution::WEEK =>
                'CONCAT(STR_TO_DATE(CONCAT(YEARWEEK(hour_timestamp, 3), " Monday"), "%x%v %W"), " 00:00:00") AS date',
            default => 'CONCAT('
                . 'YEAR(hour_timestamp),'
                . '"-",'
                . 'LPAD(MONTH(hour_timestamp),2,"0"),'
                . '"-01 00:00:00"'
                . ') as date',
        };

        $columns = [...self::AMOUNT_BY_TYPE_COLUMNS, $dateColumn];

        $dateTimeZone = new DateTimeZone($from->format('O'));
        $closure = fn() => $builder->selectRaw(join(',', $columns))
            ->groupBy('date')
            ->get();
        $rows = SqlUtils::executeTimezoneAwareQuery($dateTimeZone, $closure);

        $date = DateUtils::createSanitizedStartDate(
            $from->getTimezone(),
            $resolution,
            DateTime::createFromInterface($from),
        );

        $result = [];
        $zeroRow = [
            'DspAdvertisersExpense' => 0,
            'DspLicenseFee' => 0,
            'DspOperatorFee' => 0,
            'DspCommunityFee' => 0,
            'DspExpense' => 0,
            'SspIncome' => 0,
            'SspLicenseFee' => 0,
            'SspOperatorFee' => 0,
            'SspPublishersIncome' => 0,
        ];

        foreach ($rows as $row) {
            while ($row->date !== $date->format('Y-m-d H:i:s')) {
                $result[] = array_merge($zeroRow, ['date' => $date->format(DateTimeInterface::ATOM)]);
                DateUtils::advanceStartDate($resolution, $date);
            }
            $result[] = [
                'DspAdvertisersExpense' => (int)$row->DspAdvertisersExpense,
                'DspLicenseFee' => (int)$row->DspLicenseFee,
                'DspOperatorFee' => (int)$row->DspOperatorFee,
                'DspCommunityFee' => (int)$row->DspCommunityFee,
                'DspExpense' => (int)$row->DspExpense,
                'SspIncome' => (int)$row->SspIncome,
                'SspLicenseFee' => (int)$row->SspLicenseFee,
                'SspOperatorFee' => (int)$row->SspOperatorFee,
                'SspPublishersIncome' => (int)$row->SspPublishersIncome,
                'date' => $date->format(DateTimeInterface::ATOM),
            ];
            DateUtils::advanceStartDate($resolution, $date);
        }
        while ($date <= $to) {
            $result[] = array_merge($zeroRow, ['date' => $date->format(DateTimeInterface::ATOM)]);
            DateUtils::advanceStartDate($resolution, $date);
        }
        $result[0]['date'] = $from->format(DateTimeInterface::ATOM);
        return $result;
    }
}
