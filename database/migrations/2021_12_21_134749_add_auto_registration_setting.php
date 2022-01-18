<?php

use Adshares\Adserver\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class AddAutoRegistrationSetting extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('configs')->insert(
            [
                'key' => 'auto-registration-enabled',
                'value' => 1,
                'created_at' => new DateTime(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('configs')->where('key', 'auto-registration-enabled')->delete();
    }
}