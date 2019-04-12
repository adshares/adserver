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

    public const ADPAY_LAST_EXPORTED_EVENT_ID = 'adpay-last-exported-event-id';

    private const ADSELECT_EVENT_EXPORT_TIME = 'adselect-event-export';

    private const ADSELECT_INVENTORY_EXPORT_TIME = 'adselect-inventory-export';

    public const ADSELECT_PAYMENT_EXPORT_TIME = 'adselect-payment-export';

    public const OPERATOR_WALLET_EMAIL_LAST_TIME = 'operator-wallet-transfer-email-time';

    public const HOT_WALLET_MIN_VALUE = 'hotwallet-min-value';

    public const HOT_WALLET_MAX_VALUE = 'hotwallet-max-value';

    public const COLD_WALLET_ADDRESS = 'cold-wallet-address';

    public const COLD_WALLET_IS_ACTIVE = 'cold-wallet-is-active';

    public const ADSERVER_NAME = 'adserver-name';

    public const TECHNICAL_EMAIL = 'technical-email';

    public const SUPPORT_EMAIL = 'support-email';

    public const BONUS_NEW_USER_ENABLED = 'bonus-new-users-enabled';

    public const BONUS_NEW_USER_AMOUNT = 'bonus-new-users-amount';

    private const ADMIN_SETTINGS = [
        self::OPERATOR_TX_FEE,
        self::OPERATOR_RX_FEE,
        self::LICENCE_RX_FEE,
        self::HOT_WALLET_MIN_VALUE,
        self::HOT_WALLET_MAX_VALUE,
        self::COLD_WALLET_ADDRESS,
        self::COLD_WALLET_IS_ACTIVE,
        self::ADSERVER_NAME,
        self::TECHNICAL_EMAIL,
        self::SUPPORT_EMAIL,
        self::BONUS_NEW_USER_ENABLED,
        self::BONUS_NEW_USER_AMOUNT,
    ];

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $guarded = [];

    private static function fetchByKey(string $key, string $default = ''): string
    {
        $config = self::where('key', $key)->first();

        if ($config === null) {
            return $default;
        }

        return $config->value;
    }

    private static function updateOrInsertByKey(string $key, string $value): void
    {
        $config = self::where('key', $key)->first();

        if (!$config) {
            $config = new self();
            $config->key = $key;
        }

        $config->value = $value;
        $config->save();
    }

    public static function fetchDateTimeByKeyOrEpochStart(string $key): DateTime
    {
        $dateString = self::fetchByKey($key, '1970-01-01 00:00:00.000000');

        return DateTime::createFromFormat(DateTime::ATOM, $dateString);
    }

    public static function updateOrInsertDateTimeByKey(string $key, DateTime $date): void
    {
        self::updateOrInsertByKey($key, $date->format(DateTime::ATOM));
    }

    public static function fetchAdSelectEventExportTime(): DateTime
    {
        return self::fetchDateTimeByKeyOrEpochStart(self::ADSELECT_EVENT_EXPORT_TIME);
    }

    public static function fetchAdSelectInventoryExportTime(): DateTime
    {
        return self::fetchDateTimeByKeyOrEpochStart(self::ADSELECT_INVENTORY_EXPORT_TIME);
    }

    public static function updateAdSelectEventExportTime(DateTime $date): void
    {
        self::updateOrInsertDateTimeByKey(self::ADSELECT_EVENT_EXPORT_TIME, $date);
    }

    public static function updateAdSelectInventoryExportTime(DateTime $date): void
    {
        self::updateOrInsertDateTimeByKey(self::ADSELECT_INVENTORY_EXPORT_TIME, $date);
    }

    public static function getFee(string $feeType): float
    {
        $config = self::where('key', $feeType)->firstOrFail();

        return (float)$config->value;
    }

    public static function getLicenceAccount(): string
    {
        $config = self::where('key', self::LICENCE_ACCOUNT)->firstOrFail();

        return (string)$config->value;
    }

    public static function fetchAdminSettings(): array
    {
        $data = self::whereIn('key', self::ADMIN_SETTINGS)->get();

        return $data->pluck('value', 'key')->toArray();
    }

    public static function isColdWalletActive(): bool
    {
        $config = self::where('key', self::COLD_WALLET_IS_ACTIVE)->first();

        if (null === $config) {
            return false;
        }

        return (bool)$config->value;
    }

    public static function updateAdminSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            self::updateOrInsertByKey($key, $value);
        }
    }

    public static function fetchAdPayLastExportedEventId(): int
    {
        $id = self::where('key', self::ADPAY_LAST_EXPORTED_EVENT_ID)->first();

        if (!$id) {
            return 0;
        }

        return (int)$id->value;
    }

    public static function updateAdPayLastExportedEventId(int $id): void
    {
        self::updateOrInsertByKey(self::ADPAY_LAST_EXPORTED_EVENT_ID, (string)$id);
    }

    public static function isNewUserBonusEnabled(): bool
    {
        return (bool)self::fetchByKey(self::BONUS_NEW_USER_ENABLED);
    }

    public static function newUserBonusAmount(): int
    {
        return (int)self::fetchByKey(self::BONUS_NEW_USER_AMOUNT, '0');
    }
}
