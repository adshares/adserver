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

// TODO: do sprawdzenia dok adselect na wiki github

class CreateCampaignExcludesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('campaign_excludes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->binary('uuid', 16); // REQ CUSTOM ALTER

            $table->timestamps();
            $table->softDeletes();

            $table->bigInteger('campaign_id')->unsigned();

            $table->binary('name', 64); // REQ CUSTOM ALTER
            $table->binary('min', 64); // REQ CUSTOM ALTER
            $table->binary('max', 64); // REQ CUSTOM ALTER

            $table->foreign('campaign_id')->references('id')->on('campaigns')->onUpdate('RESTRICT')->onDelete('CASCADE');
        });

        if (DB::isMysql()) {
            DB::statement('ALTER TABLE campaign_excludes MODIFY uuid varbinary(16) NOT NULL');
            DB::statement('ALTER TABLE campaign_excludes MODIFY name varbinary(64)');
            DB::statement('ALTER TABLE campaign_excludes MODIFY min varbinary(64)');
            DB::statement('ALTER TABLE campaign_excludes MODIFY max varbinary(64)');
        }

        Schema::table('campaign_excludes', function (Blueprint $table) {
            $table->unique('uuid');
            $table->index(['campaign_id', 'name', 'min'], 'campaign_excludes_min');
            $table->index(['campaign_id', 'name', 'max'], 'campaign_excludes_max');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::drop('campaign_excludes');
    }
}
