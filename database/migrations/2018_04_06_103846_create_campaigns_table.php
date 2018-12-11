<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateCampaignsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->binary('uuid', 16); // REQ CUSTOM ALTER

            $table->timestamps();
            $table->softDeletes();

            $table->bigInteger('user_id')->unsigned();

            $table->string('landing_url', 1024);

            $table->dateTime('time_start');
            $table->dateTime('time_end')->nullable(true);

            $table->unsignedTinyInteger('status')->nullable(false)->default(0);
            $table->string('name', 255)->nullable(false)->default('<name>');
            $table->bigInteger('max_cpm')->nullable(false)->default(0);
            $table->bigInteger('max_cpc')->nullable(false)->default(0);
            $table->bigInteger('budget')->nullable(false)->default(0);

            $table->json('targeting_excludes')->nullable(true);
            $table->json('targeting_requires')->nullable(true);

            $table->unsignedTinyInteger('classification_status')->nullable(false)->default(0);
            $table->string('classification_tags')->nullable(true);

            $table->foreign('user_id')->references('id')->on('users')->onUpdate('RESTRICT')->onDelete('CASCADE');
        });

        if (DB::isMysql()) {
            DB::statement('ALTER TABLE campaigns MODIFY uuid varbinary(16) NOT NULL');
        }

        Schema::table('campaigns', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::drop('campaigns');
    }
}
