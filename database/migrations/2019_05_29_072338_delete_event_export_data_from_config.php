<?php

use Illuminate\Database\Migrations\Migration;

class DeleteEventExportDataFromConfig extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('configs')->where('key', 'adselect-event-export')->delete();
        DB::table('configs')->where('key', 'adselect-payment-export')->delete();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
