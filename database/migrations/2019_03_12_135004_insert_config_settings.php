<?php

use Adshares\Adserver\Models\Config;
use Illuminate\Database\Migrations\Migration;

class InsertConfigSettings extends Migration
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
                'key' => Config::HOT_WALLET_MIN_VALUE,
                'value' => config('app.adshares_wallet_min_amount') ?? '2000000000000000',
            ]
        );

        DB::table('configs')->insert(
            [
                'key' => Config::HOT_WALLET_MAX_VALUE,
                'value' => config('app.adshares_wallet_max_amount') ?? '50000000000000000',
            ]
        );

        DB::table('configs')->insert(
            [
                'key' => Config::ADSERVER_NAME,
                'value' => config('app.name') ?? 'AdServer',
            ]
        );

        DB::table('configs')->insert(
            [
                'key' => Config::TECHNICAL_EMAIL,
                'value' => config('app.adshares_operator_email') ?? 'mail@example.com',
            ]
        );

        DB::table('configs')->insert(
            [
                'key' => Config::SUPPORT_EMAIL,
                'value' => config('app.adshares_operator_email') ?? 'mail@example.com',
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
        DB::table('configs')->whereIn(
            'key',
            [
                Config::HOT_WALLET_MIN_VALUE,
                Config::HOT_WALLET_MAX_VALUE,
                Config::ADSERVER_NAME,
                Config::TECHNICAL_EMAIL,
                Config::SUPPORT_EMAIL,
            ]
        )->delete();
    }
}
