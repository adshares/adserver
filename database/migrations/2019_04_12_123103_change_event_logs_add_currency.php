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
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeEventLogsAddCurrency extends Migration
{
    public function up(): void
    {
        Schema::table(
            'exchange_rates',
            function (Blueprint $table) {
                $table->decimal('value', 9, 5)->change();
            }
        );

        Schema::table(
            'event_logs',
            function (Blueprint $table) {
                $table->renameColumn('licence_fee', 'license_fee');
            }
        );

        Schema::table(
            'event_logs',
            function (Blueprint $table) {
                $table->bigInteger('event_value_currency')->after('their_userdata')->unsigned()->nullable();
                $table->decimal('exchange_rate', 9, 5)->after('event_value_currency')->nullable();
            }
        );

        DB::update('UPDATE `event_logs` SET `event_value_currency` = `event_value` WHERE `event_value` IS NOT NULL');
        DB::update('UPDATE `event_logs` SET `exchange_rate` = 1 WHERE `event_value` IS NOT NULL');

        Schema::table(
            'network_event_logs',
            function (Blueprint $table) {
                $table->renameColumn('licence_fee_amount', 'license_fee');
            }
        );

        Schema::table(
            'network_event_logs',
            function (Blueprint $table) {
                $table->renameColumn('operator_fee_amount', 'operator_fee');
            }
        );

        Schema::table(
            'network_event_logs',
            function (Blueprint $table) {
                $table->decimal('exchange_rate', 9, 5)->after('paid_amount')->nullable();
                $table->bigInteger('paid_amount_currency')->after('exchange_rate')->unsigned()->nullable();
            }
        );

        DB::update('UPDATE `network_event_logs` SET `exchange_rate` = 1 WHERE `paid_amount` IS NOT NULL');
        DB::update('UPDATE `network_event_logs` SET `paid_amount_currency` = `paid_amount` WHERE `paid_amount` IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table(
            'network_event_logs',
            function (Blueprint $table) {
                $table->dropColumn('paid_amount_currency');
            }
        );

        Schema::table(
            'network_event_logs',
            function (Blueprint $table) {
                $table->dropColumn('exchange_rate');
            }
        );

        Schema::table(
            'network_event_logs',
            function (Blueprint $table) {
                $table->renameColumn('operator_fee', 'operator_fee_amount');
            }
        );

        Schema::table(
            'network_event_logs',
            function (Blueprint $table) {
                $table->renameColumn('license_fee', 'licence_fee_amount');
            }
        );

        Schema::table(
            'event_logs',
            function (Blueprint $table) {
                $table->dropColumn('exchange_rate');
            }
        );

        Schema::table(
            'event_logs',
            function (Blueprint $table) {
                $table->dropColumn('event_value_currency');
            }
        );

        Schema::table(
            'event_logs',
            function (Blueprint $table) {
                $table->renameColumn('license_fee', 'licence_fee');
            }
        );

        Schema::table(
            'exchange_rates',
            function (Blueprint $table) {
                $table->string('value')->change();
            }
        );
    }
}
