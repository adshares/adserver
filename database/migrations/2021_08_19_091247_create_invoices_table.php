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

use Adshares\Adserver\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'invoices',
            function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->binary('uuid', 16);
                $table->bigInteger('user_id')->unsigned();
                $table->string('type', 16);
                $table->string('number', 64)->unique();
                $table->timestamp('issue_date');
                $table->timestamp('due_date');
                $table->string('seller_name', 256);
                $table->string('seller_address', 256);
                $table->string('seller_postal_code', 16)->nullable();
                $table->string('seller_city', 128)->nullable();
                $table->string('seller_country', 2);
                $table->string('seller_vat_id', 32);
                $table->string('buyer_name', 256);
                $table->string('buyer_address', 512);
                $table->string('buyer_postal_code', 16)->nullable();
                $table->string('buyer_city', 128)->nullable();
                $table->string('buyer_country', 2);
                $table->string('buyer_vat_id', 32);
                $table->string('currency', 3);
                $table->decimal('net_amount', 12);
                $table->decimal('gross_amount', 12);
                $table->decimal('vat_amount', 12);
                $table->string('vat_rate', 16);
                $table->string('comments', 256)->nullable();
                $table->text('html_output');
                $table->timestamps();
                $table->softDeletes();
            }
        );
        DB::table('configs')->insert(
            [
                'key' => 'invoice-enabled',
                'value' => 0,
                'created_at' => new DateTime(),
            ]
        );
        DB::table('configs')->insert(
            [
                'key' => 'invoice-currencies',
                'value' => 'EUR,USD',
                'created_at' => new DateTime(),
            ]
        );
        DB::table('configs')->insert(
            [
                'key' => 'invoice-number-format',
                'value' => 'INV NNNN/MM/YYYY',
                'created_at' => new DateTime(),
            ]
        );
        DB::table('configs')->insert(
            [
                'key' => 'invoice-company-name',
                'value' => '',
                'created_at' => new DateTime(),
            ]
        );
        DB::table('configs')->insert(
            [
                'key' => 'invoice-company-address',
                'value' => '',
                'created_at' => new DateTime(),
            ]
        );
        DB::table('configs')->insert(
            [
                'key' => 'invoice-company-postal-code',
                'value' => '',
                'created_at' => new DateTime(),
            ]
        );
        DB::table('configs')->insert(
            [
                'key' => 'invoice-company-city',
                'value' => '',
                'created_at' => new DateTime(),
            ]
        );
        DB::table('configs')->insert(
            [
                'key' => 'invoice-company-country',
                'value' => '',
                'created_at' => new DateTime(),
            ]
        );
        DB::table('configs')->insert(
            [
                'key' => 'invoice-company-vat-id',
                'value' => '',
                'created_at' => new DateTime(),
            ]
        );
        DB::table('configs')->insert(
            [
                'key' => 'invoice-company-bank-accounts',
                'value' => '',
                'created_at' => new DateTime(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('configs')->where('key', 'invoice-enabled')->delete();
        DB::table('configs')->where('key', 'invoice-currencies')->delete();
        DB::table('configs')->where('key', 'invoice-number-format')->delete();
        DB::table('configs')->where('key', 'invoice-company-name')->delete();
        DB::table('configs')->where('key', 'invoice-company-address')->delete();
        DB::table('configs')->where('key', 'invoice-company-postal-code')->delete();
        DB::table('configs')->where('key', 'invoice-company-city')->delete();
        DB::table('configs')->where('key', 'invoice-company-country')->delete();
        DB::table('configs')->where('key', 'invoice-company-vat-id')->delete();
        DB::table('configs')->where('key', 'invoice-company-bank-accounts')->delete();
        Schema::dropIfExists('invoices');
    }
}
