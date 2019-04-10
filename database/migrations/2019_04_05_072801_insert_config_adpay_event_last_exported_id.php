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
use Adshares\Adserver\Models\Config;
use Illuminate\Database\Migrations\Migration;

class InsertConfigAdpayEventLastExportedId extends Migration
{
    private const DATABASE_DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function up(): void
    {
        $adpayEventExportTime = DB::table('configs')->where('key', 'adpay-event-export')->first();
        if (!$adpayEventExportTime) {
            DB::table('configs')->insert(
                [
                    'key' => Config::ADPAY_LAST_EXPORTED_EVENT_ID,
                    'value' => 0,
                    'created_at' => new DateTime(),
                ]
            );

            return;
        }

        $date = DateTime::createFromFormat(DateTime::ATOM, $adpayEventExportTime->value);
        $event = DB::table('event_logs')->where('created_at', '<=', $date)->orderByDesc('id')->first();
        DB::table('configs')->updateOrInsert(
            [
                'key' => Config::ADPAY_LAST_EXPORTED_EVENT_ID,
                'value' => $event->id ?? 0,
                'created_at' => new DateTime(),
            ]
        );

        DB::table('configs')->where('key', 'adpay-event-export')->delete();
    }

    public function down(): void
    {
        $adpayEventLastExportedId = DB::table('configs')->where('key', Config::ADPAY_LAST_EXPORTED_EVENT_ID)->first();
        if (!$adpayEventLastExportedId) {
            return;
        }

        $id = (int)$adpayEventLastExportedId->value;
        $event = DB::table('event_logs')->where('id', $id)->first();

        if ($event) {
            $date = DateTime::createFromFormat(self::DATABASE_DATETIME_FORMAT, $event->created_at, new DateTimeZone('UTC'));
            DB::table('configs')->insert(
                [
                    'key' => 'adpay-event-export',
                    'value' => $date->format(DATETIME::ATOM),
                    'created_at' => new DateTime(),
                ]
            );
        }

        DB::table('configs')->where('key', Config::ADPAY_LAST_EXPORTED_EVENT_ID)->delete();
    }
}
