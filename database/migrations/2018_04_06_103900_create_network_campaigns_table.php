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

use Adshares\Adserver\Models\NetworkCampaign;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateNetworkCampaignsTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('network_campaigns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->binary('uuid', 16); // REQ CUSTOM ALTER
            $table->binary('parent_uuid', 16); // REQ CUSTOM ALTER

            $table->timestamps();
            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();

            $table->string('source_host'); // should link to network_hosts

            // TODO: Jacek? do we really need that / not really seen it being used outside of prop set()
            // $table->integer('source_update_time')->unsigned(); // ? smelly
            // TODO: Jacek? why is that here and why the format is string -> is that account of the user that is doing the campaign?
            $table->string('adshares_address', 32);

            $table->string('landing_url', 1024);
            $table->string('source_version', 4)->nullable(false);

            $table->decimal('max_cpm', 19, 11)->nullable(false);
            $table->decimal('max_cpc', 19, 11)->nullable(false);
            $table->decimal('budget_per_hour', 19, 11)->nullable(false);
            $table->dateTime('time_start');
            $table->dateTime('time_end');

            $table->unsignedTinyInteger('status')->nullable(false)->default(NetworkCampaign::STATUS_ACTIVE);

            $table->json('targeting_excludes')->nullable(true);
            $table->json('targeting_requires')->nullable(true);
        });

        if (DB::isMysql()) {
            DB::statement("ALTER TABLE network_campaigns MODIFY uuid varbinary(16) NOT NULL");
            DB::statement("ALTER TABLE network_campaigns MODIFY parent_uuid varbinary(16) NOT NULL");
        }

        Schema::table('network_campaigns', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }


    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('network_campaigns');
    }
}
