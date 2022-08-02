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

namespace Adshares\Adserver\Utilities;

use Adshares\Adserver\Models\Config;
use Illuminate\Support\Facades\Config as SystemConfig;

class DatabaseConfigReader
{
    private const MAIL_KEY_MAP = [
        Config::MAIL_FROM_ADDRESS => 'mail.from.address',
        Config::MAIL_FROM_NAME => 'mail.from.name',
        Config::MAIL_MAILER => 'mail.default',
        Config::MAIL_SMTP_ENCRYPTION => 'mail.mailers.smtp.encryption',
        Config::MAIL_SMTP_HOST => 'mail.mailers.smtp.host',
        Config::MAIL_SMTP_PASSWORD => 'mail.mailers.smtp.password',
        Config::MAIL_SMTP_PORT => 'mail.mailers.smtp.port',
        Config::MAIL_SMTP_USERNAME => 'mail.mailers.smtp.username',
    ];

    private const TYPE_CONVERSIONS = [
        Config::ADSHARES_NODE_PORT => ConfigTypes::Integer,
        Config::AUTO_WITHDRAWAL_LIMIT_ADS => ConfigTypes::Integer,
        Config::AUTO_WITHDRAWAL_LIMIT_BSC => ConfigTypes::Integer,
        Config::AUTO_WITHDRAWAL_LIMIT_BTC => ConfigTypes::Integer,
        Config::AUTO_WITHDRAWAL_LIMIT_ETH => ConfigTypes::Integer,
        Config::BTC_WITHDRAW_FEE => ConfigTypes::Float,
        Config::BTC_WITHDRAW_MAX_AMOUNT => ConfigTypes::Integer,
        Config::BTC_WITHDRAW_MIN_AMOUNT => ConfigTypes::Integer,
        Config::CAMPAIGN_MIN_BUDGET => ConfigTypes::Integer,
        Config::CAMPAIGN_MIN_CPA => ConfigTypes::Integer,
        Config::CAMPAIGN_MIN_CPM => ConfigTypes::Integer,
        Config::EXCHANGE_CURRENCIES => ConfigTypes::Array,
        Config::FIAT_DEPOSIT_MAX_AMOUNT => ConfigTypes::Integer,
        Config::FIAT_DEPOSIT_MIN_AMOUNT => ConfigTypes::Integer,
        Config::HOT_WALLET_MAX_VALUE => ConfigTypes::Integer,
        Config::HOT_WALLET_MIN_VALUE => ConfigTypes::Integer,
        Config::INVENTORY_EXPORT_WHITELIST => ConfigTypes::Array,
        Config::INVENTORY_IMPORT_WHITELIST => ConfigTypes::Array,
        Config::MAX_PAGE_ZONES => ConfigTypes::Integer,
        Config::NETWORK_DATA_CACHE_TTL => ConfigTypes::Integer,
        Config::NOW_PAYMENTS_FEE => ConfigTypes::Float,
        Config::NOW_PAYMENTS_MAX_AMOUNT => ConfigTypes::Integer,
        Config::NOW_PAYMENTS_MIN_AMOUNT => ConfigTypes::Integer,
        Config::UPLOAD_LIMIT_IMAGE => ConfigTypes::Integer,
        Config::UPLOAD_LIMIT_MODEL => ConfigTypes::Integer,
        Config::UPLOAD_LIMIT_VIDEO => ConfigTypes::Integer,
        Config::UPLOAD_LIMIT_ZIP => ConfigTypes::Integer,
    ];

    public static function overwriteAdministrationConfig(): void
    {
        $settings = Config::fetchAdminSettings(true);
        foreach ($settings as $key => $value) {
            SystemConfig::set(self::mapKey($key), self::getValue($key, $value));
        }
    }

    private static function mapKey(string $key): string
    {
        if (isset(self::MAIL_KEY_MAP[$key])) {
            return self::MAIL_KEY_MAP[$key];
        }
        return 'app.' . str_replace('-', '_', $key);
    }

    private static function getValue(string $key, $value): string|array|int|null|float
    {
        if (null === $value) {
            return null;
        }

        return match (self::TYPE_CONVERSIONS[$key] ?? ConfigTypes::String) {
            ConfigTypes::Array => array_filter(explode(',', $value)),
            ConfigTypes::Float => (float)$value,
            ConfigTypes::Integer => (int)$value,
            default => (string)$value,
        };
    }
}
