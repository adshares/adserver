<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class DeleteAdselectExportDataFromConfig extends Migration
{
    private const ADSELECT_EXPORT_DATA_KEYS = [
        'adselect-event-export',
        'adselect-payment-export',
    ];

    public function up(): void
    {
        DB::table('configs')->whereIn('key', self::ADSELECT_EXPORT_DATA_KEYS)->delete();
    }

    public function down(): void
    {
        foreach (self::ADSELECT_EXPORT_DATA_KEYS as $key) {
            if (null !== ($config = DB::table('configs')->where('key', $key)->first())) {
                continue;
            }

            DB::table('configs')->insert(
                [
                    'key' => $key,
                    'value' => 0,
                    'created_at' => new DateTime(),
                ]
            );
        }
    }
}
