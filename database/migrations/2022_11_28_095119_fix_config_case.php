<?php

use Adshares\Adserver\Models\Config;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    private const LEGACY_ALLOW_ZONE_IN_IFRAME = 'allow_zone-in-iframe';
    private const LEGACY_NETWORK_DATA_CACHE_TTL = 'network_data_cache-ttl';
    private const CONFIG_KEY_MAP = [
        Config::ALLOW_ZONE_IN_IFRAME => self::LEGACY_ALLOW_ZONE_IN_IFRAME,
        Config::NETWORK_DATA_CACHE_TTL => self::LEGACY_NETWORK_DATA_CACHE_TTL,
    ];

    public function up(): void
    {
        foreach (self::CONFIG_KEY_MAP as $currentKey => $legacyKey) {
            DB::update(sprintf("UPDATE configs SET `key`='%s' where `key`='%s'", $currentKey, $legacyKey));
        }
    }

    public function down(): void
    {
        foreach (self::CONFIG_KEY_MAP as $currentKey => $legacyKey) {
            DB::update(sprintf("UPDATE configs SET `key`='%s' where `key`='%s'", $legacyKey, $currentKey));
        }
    }
};
