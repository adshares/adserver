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

class CreateNetworkBannersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('network_banners', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->binary('uuid', 16); // REQ CUSTOM ALTER

            $table->timestamps();
            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();

            $table->bigInteger('network_campaign_id')->unsigned();

            $table->string('serve_url', 1024);
            $table->string('click_url', 1024);
            $table->string('view_url', 1024);

            $table->string('creative_type', 32);
            $table->binary('creative_sha1', 20); // REQ CUSTOM ALTER

            $table->integer('creative_width');
            $table->integer('creative_height');

            $table->foreign('network_campaign_id')->references('id')->on('network_campaigns')->onUpdate('RESTRICT')->onDelete('CASCADE');
        });

        if (DB::isMysql()) {
            DB::statement('ALTER TABLE network_banners MODIFY uuid varbinary(16)');
            DB::statement('ALTER TABLE network_banners MODIFY creative_sha1 varbinary(20)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::drop('network_banners');
    }
}
