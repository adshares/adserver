<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeNetworkEventLogs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'network_event_logs',
            static function (Blueprint $table) {
                $table->binary('campaign_id', 16)->after('zone_id')->nullable();
            }
        );

        if (DB::isMySql()) {
            DB::statement('ALTER TABLE network_event_logs MODIFY campaign_id varbinary(16)');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(
            'network_event_logs',
            static function (Blueprint $table) {
                $table->dropColumn('campaign_id');
            }
        );
    }
}
