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

class CreateNetworkBannersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('network_banners', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->binary('uuid', 16);

            $table->timestamps();
            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();

            $table->bigInteger('network_campaign_id')->unsigned();

            $table->string('serve_url', 1024);
            $table->string('click_url', 1024);
            $table->string('view_url', 1024);

            $table->string('type', 32);
            $table->binary('checksum', 20);

            $table->integer('width');
            $table->integer('height');

            $table->unsignedTinyInteger('status')->nullable(false)->default(Status::STATUS_ACTIVE);

            $table->foreign('network_campaign_id')->references('id')->on('network_campaigns')->onUpdate('RESTRICT')->onDelete('CASCADE');
        });

        if (DB::isMysql()) {
            DB::statement('ALTER TABLE network_banners MODIFY uuid varbinary(16)');
            DB::statement('ALTER TABLE network_banners MODIFY checksum varbinary(20)');
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
