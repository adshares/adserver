<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Models;

use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Builder
 */
class Config extends Model
{
    public const ADS_LOG_START = 'ads-log-start';

    public const OPERATOR_TX_FEE = 'payment-tx-fee';

    public const OPERATOR_RX_FEE = 'payment-rx-fee';

    public const LICENCE_TX_FEE = 'licence-tx-fee';

    public const LICENCE_RX_FEE = 'licence-rx-fee';

    public const LICENCE_ACCOUNT = 'licence-account';

    public const ADPAY_CAMPAIGN_EXPORT_TIME = 'adpay-campaign-export';

    public const ADPAY_EVENT_EXPORT_TIME = 'adpay-event-export';

    private const ADSELECT_EVENT_EXPORT_TIME = 'adselect-event-export';

    private const ADSELECT_INVENTORY_EXPORT_TIME = 'adselect-inventory-export';

    public const ADSELECT_PAYMENT_EXPORT_TIME = 'adselect-payment-export';

    public const OPERATOR_WALLET_EMAIL_LAST_TIME = 'operator-wallet-transfer-email-time';

    public const HOT_WALLET_MIN_VALUE = 'hotwallet-min-value';
    public const HOT_WALLET_MAX_VALUE = 'hotwallet-max-value';
    public const ADSERVER_NAME = 'adserver-name';
    public const TECHNICAL_EMAIL = 'technical-email';
    public const SUPPORT_EMAIL = 'support-email';

    private const ADMIN_SETTINGS = [
        self::OPERATOR_TX_FEE,
        self::OPERATOR_RX_FEE,
        self::LICENCE_RX_FEE,
        self::HOT_WALLET_MIN_VALUE,
        self::HOT_WALLET_MAX_VALUE,
        self::ADSERVER_NAME,
        self::TECHNICAL_EMAIL,
        self::SUPPORT_EMAIL,
    ];

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $guarded = [];

    public static function fetch(string $key, string $default = ''): string
    {
        $config = self::where('key', $key)->first();

        if ($config === null) {
            return $default;
        }

        return $config->value;
    }

    public static function fetchAdSelectEventExportTime(): DateTime
    {
        return self::fetchDateTimeByKey(self::ADSELECT_EVENT_EXPORT_TIME);
    }

    public static function fetchAdSelectInventoryExportTime(): DateTime
    {
        return self::fetchDateTimeByKey(self::ADSELECT_INVENTORY_EXPORT_TIME);
    }

    public static function fetchDateTimeByKey(string $key): DateTime
    {
        $config = self::where('key', $key)->first();

        if (!$config) {
            return new DateTime('@0');
        }

        return DateTime::createFromFormat(DateTime::ATOM, $config->value);
    }

    public static function updateAdSelectEventExportTime(DateTime $date): void
    {
        self::updateDateTimeByKey(self::ADSELECT_EVENT_EXPORT_TIME, $date);
    }

    public static function updateAdSelectInventoryExportTime(DateTime $date): void
    {
        self::updateDateTimeByKey(self::ADSELECT_INVENTORY_EXPORT_TIME, $date);
    }

    public static function updateDateTimeByKey(string $key, DateTime $date): void
    {
        $config = self::where('key', $key)->first();

        if (!$config) {
            $config = new self();
            $config->key = $key;
        }

        $config->value = $date->format(DateTime::ATOM);
        $config->save();
    }

    public static function getFee(string $feeType): ?float
    {
        $config = self::where('key', $feeType)->first();

        if ($config === null) {
            return null;
        }

        return (float)$config->value;
    }

    public static function getLicenceAccount(): ?string
    {
        $config = self::where('key', self::LICENCE_ACCOUNT)->first();

        if ($config === null) {
            return null;
        }

        return (string)$config->value;
    }

    public static function fetchAdminSettings()
    {
        $data = self::whereIn('key', self::ADMIN_SETTINGS)->get();

        return $data->pluck('value', 'key')->toArray();
    }

    public static function updateAdminSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $config = self::where('key', $key)->first();
            $config->value = $value;
            $config->update();
        }
    }
}
