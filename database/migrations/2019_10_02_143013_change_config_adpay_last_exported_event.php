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

class ChangeConfigAdpayLastExportedEvent extends Migration
{
    public function up(): void
    {
        DB::table('configs')
            ->whereIn('key', ['adpay-last-exported-event-id', Config::ADPAY_CAMPAIGN_EXPORT_TIME])
            ->delete();
    }

    public function down(): void
    {
        $date = new DateTime('-2 hours');
        $eventId = DB::table('event_logs')->where('created_at', '<=', $date)->max('id') ?? 0;
        $current = new DateTime();

        DB::table('configs')->updateOrInsert(
            [
                'key' => 'adpay-last-exported-event-id',
            ],
            [
                'value' => $eventId,
                'created_at' => $current,
                'updated_at' => $current,
            ]
        );

        DB::table('configs')->where('key', Config::ADPAY_LAST_EXPORTED_EVENT_TIME)->delete();
    }
}
