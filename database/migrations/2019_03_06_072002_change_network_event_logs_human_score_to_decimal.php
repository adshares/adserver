<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeNetworkEventLogsHumanScoreToDecimal extends Migration
{
    public function up(): void
    {
        Schema::table(
            'network_event_logs',
            function (Blueprint $table) {
                $table->dropColumn('human_score');
            }
        );
        Schema::table(
            'network_event_logs',
            function (Blueprint $table) {
                $table->decimal('human_score', 3, 2)->after('context')->nullable();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'event_logs',
            function (Blueprint $table) {
                $table->dropColumn('human_score');
            }
        );
        Schema::table(
            'event_logs',
            function (Blueprint $table) {
                $table->integer('human_score')->after('context')->nullable();
            }
        );
    }
}
