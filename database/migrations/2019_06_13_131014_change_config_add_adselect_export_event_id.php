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
    public function up(): void
    {
        $keys = ['adselect-event-export', 'adselect-payment-export'];

        foreach ($keys as $key) {
            $config = DB::table('configs')->where('key', $key)->first();

            if (null !== $config) {
                continue;
            }

            DB::table('configs')->insert(
                [
                    'key' => $key,
                    'value' => $this->getLastExportedId($key),
                    'created_at' => new DateTime(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('configs')->whereIn('key', ['adselect-event-export', 'adselect-payment-export'])->delete();
    }

    private function getLastExportedId(string $key): int
    {
        $column = ('adselect-payment-export' === $key) ? 'ads_payment_id' : 'id';

        return DB::table('network_event_logs')->max($column) ?? 0;
    }
}
