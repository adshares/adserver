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

class ChangeConfigAddAdselectExportEventId extends Migration
{
    private const CONFIG_KEY_ADSELECT_PAID_EVENT_ID = 'adselect-payment-export';

    public function up(): void
    {
        $config = DB::table('configs')
            ->where('key', self::CONFIG_KEY_ADSELECT_PAID_EVENT_ID)
            ->first();

        if (null !== $config) {
            return;
        }

        DB::table('configs')->insert(
            [
                'key' => self::CONFIG_KEY_ADSELECT_PAID_EVENT_ID,
                'value' => $this->getLastNetworkEventId(),
                'created_at' => new DateTime(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('configs')->where('key', self::CONFIG_KEY_ADSELECT_PAID_EVENT_ID)->delete();
    }

    private function getLastNetworkEventId(): int
    {
        return DB::table('network_event_logs')->max('id') ?? 0;
    }
}
