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

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\BannerClassification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBannerClassificationsTable extends Migration
{
    public function up(): void
    {
        Schema::create(
            'banner_classifications',
            function (Blueprint $table) {
                $table->increments('id');
                $table->bigInteger('banner_id')->unsigned();
                $table->string('classifier', 32);
                $table->json('keywords')->nullable();
                $table->binary('signature')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->timestamps();
                $table->timestamp('requested_at')->nullable();
                $table->unsignedTinyInteger('status')->default(BannerClassification::STATUS_NEW);

                $table->foreign('banner_id')
                    ->references('id')
                    ->on('banners')
                    ->onUpdate('RESTRICT')
                    ->onDelete('CASCADE');
            }
        );

        if (DB::isMysql()) {
            DB::statement('ALTER TABLE `banner_classifications` MODIFY `signature` binary(64)');
        }

        Schema::table(
            'banner_classifications',
            function (Blueprint $table) {
                $table->unique(['banner_id', 'classifier']);
                $table->index('status');
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('banner_classifications');
    }
}
