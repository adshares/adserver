<?php

use Adshares\Adserver\Models\Config;
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
        DB::table('configs')->where('key', Config::ADSELECT_EVENT_EXPORT_TIME)->delete();
        DB::table('configs')->where('key', Config::ADSELECT_LAST_EXPORTED_ADS_PAYMENT_ID)->delete();
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
