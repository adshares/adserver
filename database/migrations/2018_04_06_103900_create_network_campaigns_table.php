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

use Adshares\Supply\Domain\ValueObject\Status;
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
            $table->binary('uuid', 16);
            $table->binary('demand_campaign_id', 16);
            $table->binary('publisher_id', 16);

            $table->timestamps();

            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->string('source_host');
            $table->string('source_version', 16)->nullable(false);
            $table->string('source_address', 32);

            $table->string('landing_url', 1024);
            $table->bigInteger('max_cpm')->nullable(false);
            $table->bigInteger('max_cpc')->nullable(false);
            $table->bigInteger('budget')->nullable(false);
            $table->dateTime('date_start');
            $table->dateTime('date_end')->nullable(true);

            $table->unsignedTinyInteger('status')->nullable(false)->default(Status::STATUS_ACTIVE);

            $table->json('targeting_excludes')->nullable(true);
            $table->json('targeting_requires')->nullable(true);
        });

        if (DB::isMysql()) {
            DB::statement("ALTER TABLE network_campaigns MODIFY uuid varbinary(16) NOT NULL");
            DB::statement("ALTER TABLE network_campaigns MODIFY demand_campaign_id varbinary(16) NOT NULL");
            DB::statement("ALTER TABLE network_campaigns MODIFY publisher_id varbinary(16) NOT NULL");
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
