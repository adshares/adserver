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
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->binary('uuid', 16);
            $table->string('email', 191)->unique();

            $table->timestamps();
            $table->softDeletes();
            $table->timestamp('email_confirmed_at')->nullable();

            $table->string('name')->nullable();
            $table->string('password');

            $table->boolean('is_advertiser')->nullable();
            $table->boolean('is_publisher')->nullable();
            $table->boolean('is_admin')->default(false);
        });

        if (DB::isMysql()) {
            DB::statement('ALTER TABLE users MODIFY uuid varbinary(16) NOT NULL');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
