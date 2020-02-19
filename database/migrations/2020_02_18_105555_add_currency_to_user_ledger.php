<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddCurrencyToUserLedger extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_ledger_entries', function (Blueprint $table) {
            $table->string('currency', 6)->default('ADS');
            $table->bigInteger('currency_amount')->nullable();
            $table->string('address_from', 36)->nullable()->change();
            $table->string('address_to', 36)->nullable()->change();
        });
        DB::update('UPDATE user_ledger_entries SET currency_amount = amount');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_ledger_entries', function (Blueprint $table) {
            $table->dropColumn('currency');
            $table->dropColumn('currency_amount');
            $table->string('address_from', 18)->nullable()->change();
            $table->string('address_to', 18)->nullable()->change();
        });
    }
}
