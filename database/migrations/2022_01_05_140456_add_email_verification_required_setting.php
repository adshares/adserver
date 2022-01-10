<?php

use Adshares\Adserver\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class AddEmailVerificationRequiredSetting extends Migration
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
                'key' => 'email-verification-required',
                'value' => 0,
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
        DB::table('configs')->where('key', 'email-verification-required')->delete();
    }
}
