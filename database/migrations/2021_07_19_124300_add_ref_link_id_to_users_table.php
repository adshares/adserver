<?php
/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\RefLink;
use Adshares\Adserver\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRefLinkIdToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach (User::withTrashed()->get() as $user) {
            $reflink = new RefLink();
            $reflink->user_id = $user->id;
            $reflink->token = Utils::urlSafeBase64Encode(hex2bin($user->uuid));
            $reflink->saveOrFail();
        }

        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('ref_link_id')->nullable(true);
        });

        foreach (User::whereNotNull('referrer_user_id')->get() as $user) {
            $user->ref_link_id = RefLink::where('user_id', $user->referrer_user_id)->first()->id;
            $user->saveOrFail();
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('referrer_user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('referrer_user_id')->nullable(true);
        });

        foreach (User::whereNotNull('ref_link_id')->get() as $user) {
            $user->referrer_user_id = optional($user->refLink)->user_id;
            $user->saveOrFail();
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('ref_link_id');
        });

        RefLink::truncate();
    }
}
