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

use Adshares\Adserver\Models\PaymentReport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreatePaymentReportsTable extends Migration
{
    private const HOUR = PaymentReport::MIN_INTERVAL;

    public function up(): void
    {
        Schema::create(
            'payment_reports',
            function (Blueprint $table) {
                $table->bigInteger('id')->primary();
                $table->timestamps();
                $table->unsignedTinyInteger('status');
            }
        );

        $this->insertFirstReport();
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reports');
    }

    private function insertFirstReport(): void
    {
        $timestamp = ((int)floor(time() / self::HOUR) - 1) * self::HOUR;
        $dateTime = new DateTime();
        DB::table('payment_reports')->insert(
            [
                'id' => $timestamp,
                'status' => PaymentReport::STATUS_NEW,
                'created_at' => $dateTime,
                'updated_at' => $dateTime,
            ]
        );
    }
}
