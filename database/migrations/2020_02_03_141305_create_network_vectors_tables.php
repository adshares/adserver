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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNetworkVectorsTables extends Migration
{
    private const TABLE_VECTORS = 'network_vectors';

    private const TABLE_VECTORS_METAS = 'network_vectors_metas';

    public function up(): void
    {
        $keyLengthMaximum = strlen('site:domain:') + 255;

        Schema::create(
            self::TABLE_VECTORS_METAS,
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('network_host_id')->unique();
                $table->timestamps();
                $table->unsignedBigInteger('total_events_count');
            }
        );

        Schema::create(
            self::TABLE_VECTORS,
            function (Blueprint $table) use ($keyLengthMaximum) {
                $table->bigIncrements('id');
                $table->bigInteger('network_host_id');
                $table->string('key', $keyLengthMaximum);
                $table->unsignedMediumInteger('occurrences');
                $table->bigInteger('cpm_25');
                $table->bigInteger('cpm_50');
                $table->bigInteger('cpm_75');
                $table->bigInteger('negation_cpm_25');
                $table->bigInteger('negation_cpm_50');
                $table->bigInteger('negation_cpm_75');
                $table->binary('data');

                $table->index(['network_host_id', 'key']);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE_VECTORS);
        Schema::dropIfExists(self::TABLE_VECTORS_METAS);
    }
}
