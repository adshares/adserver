<?php

use Adshares\Adserver\Models\UserLedgerEntry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToUserLedgerEntries extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_ledger_entries', function (Blueprint $table) {
            $table->index('user_id', UserLedgerEntry::INDEX_USER_ID);
            $table->index('created_at', UserLedgerEntry::INDEX_CREATED_AT);
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
            $table->dropIndex(UserLedgerEntry::INDEX_USER_ID);
            $table->dropIndex(UserLedgerEntry::INDEX_CREATED_AT);
        });
    }
}
