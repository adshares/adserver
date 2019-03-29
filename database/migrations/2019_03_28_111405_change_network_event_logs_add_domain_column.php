<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ChangeNetworkEventLogsAddDomainColumn extends Migration
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
            function (Blueprint $table) {
                $table->string('domain', 255)->nullable();
            }
        );
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
            function (Blueprint $table) {
                $table->dropColumn('domain');
            }
        );
    }
}
