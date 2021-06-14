<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

use Adshares\Adserver\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class ChangeConfigEntryForAdselectExport extends Migration
{
    private const DATABASE_DATETIME_FORMAT = 'Y-m-d H:i:s';
    private const ADSELECT_LAST_EXPORTED_ADS_PAYMENT_ID = 'adselect-payment-export';


    public function up(): void
    {
        $adselectPaymentExportDate = DB::table('configs')
            ->where('key', self::ADSELECT_LAST_EXPORTED_ADS_PAYMENT_ID)
            ->first();

        if (null === $adselectPaymentExportDate) {
            return;
        }

        $dateTime = DateTime::createFromFormat(DateTimeInterface::ATOM, $adselectPaymentExportDate->value);
        $dateTimeCutoff = (clone $dateTime)->modify('-7 days');

        $adsPaymentId = DB::table('network_event_logs')
            ->where('created_at', '>', $dateTimeCutoff)
            ->where('updated_at', '<=', $dateTime)
            ->whereNotNull('ads_payment_id')
            ->max('ads_payment_id');

        if (null === $adsPaymentId) {
            return;
        }

        DB::table('configs')
            ->where('key', self::ADSELECT_LAST_EXPORTED_ADS_PAYMENT_ID)
            ->update(
                [
                    'value' => $adsPaymentId,
                    'updated_at' => new DateTime(),
                ]
            );
    }

    public function down(): void
    {
        $adselectPaymentExportAdsPaymentId = DB::table('configs')
            ->where('key', self::ADSELECT_LAST_EXPORTED_ADS_PAYMENT_ID)
            ->first();

        if (null === $adselectPaymentExportAdsPaymentId) {
            return;
        }

        $adsPaymentId = (int)$adselectPaymentExportAdsPaymentId->value;

        $updatedAt = DB::table('network_event_logs')
            ->where('ads_payment_id', $adsPaymentId)
            ->max('updated_at');

        if (null === $updatedAt) {
            return;
        }

        $dateTime = DateTime::createFromFormat(self::DATABASE_DATETIME_FORMAT, $updatedAt);

        DB::table('configs')
            ->where('key', self::ADSELECT_LAST_EXPORTED_ADS_PAYMENT_ID)
            ->update(
                [
                    'value' => $dateTime->format(DateTimeInterface::ATOM),
                    'updated_at' => new DateTime(),
                ]
            );
    }
}
