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

use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Services\Supply\SiteClassificationUpdater;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeClassificationsDropSignature extends Migration
{
    /** @var SiteClassificationUpdater */
    private $siteClassificationUpdater;

    public function __construct()
    {
        $this->siteClassificationUpdater = new SiteClassificationUpdater();
    }

    public function up(): void
    {
        Schema::table('classifications', function (Blueprint $table) {
            $table->dropColumn('signature');
        });

        foreach (Site::all() as $site) {
            $this->siteClassificationUpdater->addInternalClassificationToFiltering($site);
        }
    }

    public function down(): void
    {
        Schema::table('classifications', function (Blueprint $table) {
            $table->string('signature', 128)->after('banner_id')->default('00');
        });
    }
}
