<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

use Adshares\Adserver\Models\Site;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSitesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->binary('uuid', 16);

            $table->timestamps();
            $table->softDeletes();

            $table->bigInteger('user_id')->unsigned()->nullable();
            $table->string('name', 64);
            $table->unsignedTinyInteger('status')
                ->nullable(false)
                ->default(Site::STATUS_INACTIVE);
            $table->json('site_excludes')->nullable(true);
            $table->json('site_requires')->nullable(true);
            $table->string('primary_language', '2')->nullable(false)->default('en');

            $table->foreign('user_id')->references('id')->on('users')->onUpdate('RESTRICT')->onDelete('RESTRICT');
        });

        if (DB::isMySql()) {
            DB::statement('ALTER TABLE sites MODIFY uuid varbinary(16)');
        }

        Schema::table('sites', function (Blueprint $table) {
            $table->unique('uuid');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::drop('sites');
    }
}
