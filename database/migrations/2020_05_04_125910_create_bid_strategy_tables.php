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

use Adshares\Adserver\Models\BidStrategy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateBidStrategyTables extends Migration
{
    public function up(): void
    {
        Schema::create(
            'bid_strategy',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('user_id')->index();
                $table->string('name');
                $table->timestamps();

                $table->index('updated_at');
            }
        );

        Schema::create(
            'bid_strategy_details',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('bid_strategy_id');
                $table->string('category', 267);
                $table->decimal('rank', 3, 2);
                $table->foreign('bid_strategy_id')
                    ->references('id')
                    ->on('bid_strategy')
                    ->onUpdate('RESTRICT')
                    ->onDelete('CASCADE');
            }
        );

        $now = new DateTime();
        DB::insert(
            'INSERT INTO bid_strategy (user_id,name,created_at,updated_at) VALUES (?,?,?,?)',
            [
                BidStrategy::ADMINISTRATOR_ID,
                "Server Default",
                $now,
                $now,
            ]
        );

        $row = DB::table('bid_strategy')->select(['id'])->first();
        $bidStrategyId = $row->id;

        Schema::table(
            'campaigns',
            function (Blueprint $table) use ($bidStrategyId) {
                $table->unsignedBigInteger('bid_strategy_id')->default($bidStrategyId);
            }
        );
        // todo change update time for all campaigns to force export to adpay
    }

    public function down(): void
    {
        Schema::table(
            'campaigns',
            function (Blueprint $table) {
                $table->dropColumn('bid_strategy_id');
            }
        );
        Schema::dropIfExists('bid_strategy_details');
        Schema::dropIfExists('bid_strategy');
    }
}
