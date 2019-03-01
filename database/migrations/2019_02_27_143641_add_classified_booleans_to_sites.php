<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddClassifiedBooleansToSites extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->boolean('require_classified')->default('0');
            $table->boolean('exclude_unclassified')->default('0');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('require_classified');
            $table->dropColumn('exclude_unclassified');
        });
    }
}
