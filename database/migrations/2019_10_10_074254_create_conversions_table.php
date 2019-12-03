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
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConversionsTable extends Migration
{
    public function up(): void
    {
        Schema::create(
            'conversions',
            static function (Blueprint $table) {
                $table->increments('id');
                $table->binary('uuid');
                $table->timestamps();

                $table->bigInteger('event_logs_id')->unsigned()->index();
                $table->binary('case_id');
                $table->binary('group_id');
                $table->bigInteger('conversion_definition_id')->unsigned();
                $table->bigInteger('value')->unsigned()->nullable();
                $table->decimal('weight', 3, 2);

                $table->tinyInteger('payment_status')->unsigned()->nullable();
                $table->bigInteger('event_value_currency')->unsigned()->nullable();
                $table->decimal('exchange_rate', 9, 5)->nullable();
                $table->bigInteger('event_value')->unsigned()->nullable();
                $table->bigInteger('license_fee')->unsigned()->nullable();
                $table->bigInteger('operator_fee')->unsigned()->nullable();
                $table->bigInteger('paid_amount')->unsigned()->nullable();
                $table->integer('payment_id')->nullable()->index();
                $table->binary('pay_to')->nullable();

                $table->index('created_at');
            }
        );

        if (DB::isMysql()) {
            DB::statement('ALTER TABLE conversions MODIFY uuid varbinary(16) NOT NULL');
            DB::statement('ALTER TABLE conversions MODIFY case_id varbinary(16) NOT NULL');
            DB::statement('ALTER TABLE conversions MODIFY group_id varbinary(16) NOT NULL');
            DB::statement('ALTER TABLE conversions MODIFY pay_to varbinary(6)');
        }

        Schema::table('conversions', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversions');
    }
}
