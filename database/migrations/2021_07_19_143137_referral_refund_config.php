<?php
/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\UserLedgerEntry;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ReferralRefundConfig extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('configs')->where('key', 'bonus-new-users-enabled')->delete();
        DB::table('configs')->where('key', 'bonus-new-users-amount')->delete();
        DB::table('configs')->insert(
            [
                'key' => 'referral-refund-enabled',
                'value' => 0,
                'created_at' => new DateTime(),
            ]
        );
        DB::table('configs')->insert(
            [
                'key' => 'referral-refund-commission',
                'value' => 0,
                'created_at' => new DateTime(),
            ]
        );
        Schema::table('user_ledger_entries', function (Blueprint $table) {
            $table->bigInteger('ref_link_id')->nullable(true);
            $table->index('ref_link_id', UserLedgerEntry::INDEX_REF_LINK_ID);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_ledger_entries', function (Blueprint $table) {
            $table->dropIndex(UserLedgerEntry::INDEX_REF_LINK_ID);
            $table->dropColumn('ref_link_id');
        });
        DB::table('configs')->where('key', 'referral-refund-enabled')->delete();
        DB::table('configs')->where('key', 'referral-refund-commission')->delete();
        DB::table('configs')->insert(
            [
                'key' => 'bonus-new-users-enabled',
                'value' => 0,
                'created_at' => new DateTime(),
            ]
        );
        DB::table('configs')->insert(
            [
                'key' => 'bonus-new-users-amount',
                'value' => 0,
                'created_at' => new DateTime(),
            ]
        );
    }
}
