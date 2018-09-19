<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute it and/or modify it
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
 * along with AdServer.  If not, see <https://www.gnu.org/licenses/>
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateEventLogsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('event_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->timestamps();

            $table->binary('cid', 16); // REQ CUSTOM ALTER -> dla kazdej zony oddzielnie
            $table->binary('tid', 16); // REQ CUSTOM ALTER ->

            $table->integer('publisher_event_id')->nullable();

            $table->bigInteger('banner_id'); // TODO: brakuje klucza
            $table->string('event_type', 16); // na razie jest view i click

            $table->binary('pay_to', 6)->nullable(); // REQ CUSTOM ALTER

            $table->binary('ip', 8); // REQ CUSTOM ALTER

            $table->text('our_context')->nullable(); // before aduser (browser info)
            $table->text('their_context')->nullable(); // before aduser (browser info)

            # ADUSER FUTURE (IAB data set - w zeszycie)
            $table->binary('user_id', 16)->nullable(); // REQ CUSTOM ALTER
            $table->integer('human_score')->nullable();
            $table->text('our_userdata')->nullable();
            $table->text('their_userdata')->nullable();

            $table->decimal('event_value', 20, 9)->nullable(); // na razie jest ADST - ale waluta jest potrzebna
            $table->decimal('paid_amount', 20, 9)->nullable();
            // faktycznie zaplacone
            $table->integer('payment_id')->nullable();
        });

        if (DB::isMysql()) {
            DB::statement("ALTER TABLE event_logs MODIFY cid varbinary(16)");
            DB::statement("ALTER TABLE event_logs MODIFY tid varbinary(16)");
            DB::statement("ALTER TABLE event_logs MODIFY pay_to varbinary(6)");
            DB::statement("ALTER TABLE event_logs MODIFY ip varbinary(8)");
            DB::statement("ALTER TABLE event_logs MODIFY user_id varbinary(16)");
        }
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('event_logs');
    }
}
