<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bridge_payments', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('address', 18);
            $table->string('payment_id');
            $table->timestamp('payment_time');
            $table->bigInteger('amount')->nullable();
            $table->tinyInteger('status')->default(0);
            $table->integer('last_offset')->default(0);

            $table->unique(['address', 'payment_id']);
            $table->index('status', 'bridge_payments_status_index');
        });

        Schema::table('network_case_payments', function (Blueprint $table) {
            $table->bigInteger('ads_payment_id')->nullable()->change();
            $table->bigInteger('bridge_payment_id')->after('ads_payment_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        DB::delete('DELETE FROM network_case_payments WHERE ads_payment_id IS NULL');
        Schema::table('network_case_payments', function (Blueprint $table) {
            $table->bigInteger('ads_payment_id')->nullable(false)->change();
            $table->dropColumn('bridge_payment_id');
        });

        Schema::dropIfExists('bridge_payments');
    }
};