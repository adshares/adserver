<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddErrorCodeToNetworkHostsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('network_hosts', function (Blueprint $table) {
            $table->unsignedTinyInteger('failed_connection')->nullable(false)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('network_hosts', function (Blueprint $table) {
            $table->dropColumn('failed_connection');
        });
    }
}
