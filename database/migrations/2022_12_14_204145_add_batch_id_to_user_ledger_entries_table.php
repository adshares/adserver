<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_ledger_entries', function (Blueprint $table) {
            $table->char('batchid', 64)->nullable();
            $table->index(['batchid']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_ledger_entries', function (Blueprint $table) {
            $table->dropIndex('batchid');
            $table->dropColumn(['batchid']);
        });
    }
};
