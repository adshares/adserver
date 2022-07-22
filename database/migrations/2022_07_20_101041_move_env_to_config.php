<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

use Adshares\Adserver\Models\Config;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MoveEnvToConfig extends Migration
{
    private const CE_LICENSE_ACCOUNT = '0001-00000024-FF89';
    private const CE_LICENSE_FEE = '0.01';
    private const ENVIRONMENT_VARIABLES_MIGRATION = [
        'ADSHARES_SECRET' => Config::ADSHARES_SECRET,
        'ADSHARES_ADDRESS' => Config::ADSHARES_ADDRESS,
        'ADSHARES_NODE_HOST' => Config::ADSHARES_NODE_HOST,
        'ADSHARES_NODE_PORT' => Config::ADSHARES_NODE_PORT,
        'ALLOW_ZONE_IN_IFRAME' => Config::ALLOW_ZONE_IN_IFRAME,
        'BANNER_FORCE_HTTPS' => Config::BANNER_FORCE_HTTPS,
        'BTC_WITHDRAW' => Config::BTC_WITHDRAW,
        'BTC_WITHDRAW_FEE' => Config::BTC_WITHDRAW_FEE,
        'BTC_WITHDRAW_MAX_AMOUNT' => Config::BTC_WITHDRAW_MAX_AMOUNT,
        'BTC_WITHDRAW_MIN_AMOUNT' => Config::BTC_WITHDRAW_MIN_AMOUNT,
        'CAMPAIGN_MIN_BUDGET' => Config::CAMPAIGN_MIN_BUDGET,
        'CAMPAIGN_MIN_CPA' => Config::CAMPAIGN_MIN_CPA,
        'CAMPAIGN_MIN_CPM' => Config::CAMPAIGN_MIN_CPM,
        'CAMPAIGN_TARGETING_EXCLUDE' => Config::CAMPAIGN_TARGETING_EXCLUDE,
        'CAMPAIGN_TARGETING_REQUIRE' => Config::CAMPAIGN_TARGETING_REQUIRE,
        'CDN_PROVIDER' => Config::CDN_PROVIDER,
        'CHECK_ZONE_DOMAIN' => Config::CHECK_ZONE_DOMAIN,
        'CRM_MAIL_ADDRESS_ON_CAMPAIGN_CREATED' => Config::CRM_MAIL_ADDRESS_ON_CAMPAIGN_CREATED,
        'CRM_MAIL_ADDRESS_ON_SITE_ADDED' => Config::CRM_MAIL_ADDRESS_ON_SITE_ADDED,
        'CRM_MAIL_ADDRESS_ON_USER_REGISTERED' => Config::CRM_MAIL_ADDRESS_ON_USER_REGISTERED,
        'EXCHANGE_API_KEY' => Config::EXCHANGE_API_KEY,
        'EXCHANGE_API_SECRET' => Config::EXCHANGE_API_SECRET,
        'EXCHANGE_API_URL' => Config::EXCHANGE_API_URL,
        'EXCHANGE_CURRENCIES' => Config::EXCHANGE_CURRENCIES,
        'NETWORK_DATA_CACHE_TTL' => Config::NETWORK_DATA_CACHE_TTL,
        'NOW_PAYMENTS_API_KEY' => Config::NOW_PAYMENTS_API_KEY,
        'NOW_PAYMENTS_CURRENCY' => Config::NOW_PAYMENTS_CURRENCY,
        'NOW_PAYMENTS_EXCHANGE' => Config::NOW_PAYMENTS_EXCHANGE,
        'NOW_PAYMENTS_FEE' => Config::NOW_PAYMENTS_FEE,
        'NOW_PAYMENTS_IPN_SECRET' => Config::NOW_PAYMENTS_IPN_SECRET,
        'NOW_PAYMENTS_MAX_AMOUNT' => Config::NOW_PAYMENTS_MAX_AMOUNT,
        'NOW_PAYMENTS_MIN_AMOUNT' => Config::NOW_PAYMENTS_MIN_AMOUNT,
        'SITE_FILTERING_EXCLUDE' => Config::SITE_FILTERING_EXCLUDE,
        'SITE_FILTERING_REQUIRE' => Config::SITE_FILTERING_REQUIRE,
        'UPLOAD_LIMIT_IMAGE' => Config::UPLOAD_LIMIT_IMAGE,
        'UPLOAD_LIMIT_MODEL' => Config::UPLOAD_LIMIT_MODEL,
        'UPLOAD_LIMIT_VIDEO' => Config::UPLOAD_LIMIT_VIDEO,
        'UPLOAD_LIMIT_ZIP' => Config::UPLOAD_LIMIT_ZIP,
    ];

    public function up(): void
    {
        self::fillMissingDates();
        self::deleteFromConfigs([Config::LICENCE_ACCOUNT, Config::LICENCE_RX_FEE, Config::LICENCE_TX_FEE]);

        Schema::table(
            'configs',
            function (Blueprint $table) {
                $table->text('value')->change();
            }
        );
        $this->migrateEnvironmentVariables();
    }

    public function down(): void
    {
        $this->revertEnvironmentVariablesMigration();
        Schema::table(
            'configs',
            function (Blueprint $table) {
                $table->string('value')->change();
            }
        );

        Config::updateOrCreate(
            ['key' => Config::LICENCE_ACCOUNT],
            ['value' => self::CE_LICENSE_ACCOUNT]
        );
        Config::updateOrCreate(
            ['key' => Config::LICENCE_RX_FEE],
            ['value' => self::CE_LICENSE_FEE]
        );
        Config::updateOrCreate(
            ['key' => Config::LICENCE_TX_FEE],
            ['value' => self::CE_LICENSE_FEE]
        );
    }

    private static function fillMissingDates(): void
    {
        DB::update('UPDATE configs SET created_at = updated_at WHERE created_at IS NULL');
        DB::update('UPDATE configs SET updated_at = created_at WHERE updated_at IS NULL');
    }

    private function migrateEnvironmentVariables(): void
    {
        $settings = [];
        foreach (self::ENVIRONMENT_VARIABLES_MIGRATION as $envKey => $configKey) {
            if (null !== ($envValue = env($envKey))) {
                $settings[$configKey] = $envValue;
            }
        }
        Config::updateAdminSettings($settings);
    }

    private function revertEnvironmentVariablesMigration(): void
    {
        self::deleteFromConfigs(array_values(self::ENVIRONMENT_VARIABLES_MIGRATION));
    }

    private function deleteFromConfigs(array $keys): void
    {
        $quotedKeys = array_map(
            fn($item) => sprintf("'%s'", $item),
            $keys
        );

        $sql = sprintf('DELETE FROM configs WHERE `key` IN (%s);', implode(',', $quotedKeys));

        DB::delete($sql);
    }
}
