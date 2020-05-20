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
use Adshares\Adserver\Models\BidStrategy;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Utilities\UuidStringGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBidStrategyTables extends Migration
{
    public function up(): void
    {
        Schema::create(
            'bid_strategy',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->binary('uuid');
                $table->bigInteger('user_id')->index();
                $table->string('name');
                $table->timestamps();

                $table->index('updated_at');
            }
        );

        if (DB::isMySql()) {
            DB::statement('ALTER TABLE bid_strategy MODIFY uuid VARBINARY(16) NOT NULL');
        }

        Schema::table(
            'bid_strategy',
            function (Blueprint $table) {
                $table->unique('uuid');
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

        $bidStrategyUuidHexadecimal = UuidStringGenerator::v4();
        $bidStrategyUuidBinary = hex2bin($bidStrategyUuidHexadecimal);
        $now = new DateTime();
        DB::insert(
            'INSERT INTO bid_strategy (uuid,user_id,name,created_at,updated_at) VALUES (?,?,?,?,?)',
            [$bidStrategyUuidBinary, BidStrategy::ADMINISTRATOR_ID, 'Server Default', $now, $now]
        );
        DB::insert(
            'INSERT INTO configs (`key`, value, created_at, updated_at) VALUES (?,?,?,?)',
            [Config::BID_STRATEGY_UUID_DEFAULT, $bidStrategyUuidHexadecimal, $now, $now]
        );

        Schema::table(
            'campaigns',
            function (Blueprint $table) {
                $table->binary('bid_strategy_uuid');
            }
        );

        if (DB::isMySql()) {
            DB::statement('ALTER TABLE campaigns MODIFY bid_strategy_uuid VARBINARY(16) NOT NULL');
        }
        DB::update('UPDATE campaigns SET bid_strategy_uuid=?', [$bidStrategyUuidBinary]);
        DB::delete('DELETE FROM configs WHERE `key`=?', [Config::ADPAY_CAMPAIGN_EXPORT_TIME]);
    }

    public function down(): void
    {
        Schema::table(
            'campaigns',
            function (Blueprint $table) {
                $table->dropColumn('bid_strategy_uuid');
            }
        );
        DB::delete('DELETE FROM configs WHERE `key`=?', [Config::BID_STRATEGY_UUID_DEFAULT]);
        Schema::dropIfExists('bid_strategy_details');
        Schema::dropIfExists('bid_strategy');
    }
}
