<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTypeIndexToUserLedgerEntriesTable extends Migration
{
    private const INDEX_FOR_COLUMN_TYPE = 'user_ledger_entry_type_index';

    public function up(): void
    {
        Schema::table('user_ledger_entries', function (Blueprint $table) {
            $table->index('type', self::INDEX_FOR_COLUMN_TYPE);
        });
    }

    public function down(): void
    {
        Schema::table('user_ledger_entries', function (Blueprint $table) {
            $table->dropIndex(self::INDEX_FOR_COLUMN_TYPE);
        });
    }
}
