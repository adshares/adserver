<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

use Adshares\Adserver\Models\Config;
use Adshares\Common\Application\Service\AdUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddRankToSites extends Migration
{
    public function up(): void
    {
        Schema::table(
            'sites',
            function (Blueprint $table) {
                $table->string('url', 1024);
                $table->decimal('rank', 3, 2)->default(0);
                $table->enum('info', AdUser::PAGE_INFOS)->index()->default(AdUser::PAGE_INFO_UNKNOWN);
            }
        );

        DB::update('UPDATE `sites` SET `url` = CONCAT("https://", `domain`) WHERE `domain` <> ""');

        Config::upsertDateTime(Config::SITE_VERIFICATION_NOTIFICATION_TIME_THRESHOLD, new DateTime());
    }

    public function down(): void
    {
        DB::table('configs')->where('key', Config::SITE_VERIFICATION_NOTIFICATION_TIME_THRESHOLD)->delete();

        Schema::table(
            'sites',
            function (Blueprint $table) {
                $table->dropColumn(['url', 'rank', 'info']);
            }
        );
    }
}
