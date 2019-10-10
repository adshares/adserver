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

use Adshares\Common\Exception\RuntimeException;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use function array_merge;
use function in_array;
use function sprintf;

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

    public const ADPAY_LAST_EXPORTED_CONVERSION_TIME = 'adpay-last-conversion-time';

    public const ADPAY_LAST_EXPORTED_EVENT_TIME = 'adpay-last-event-time';

    public const ADSELECT_INVENTORY_EXPORT_TIME = 'adselect-inventory-export';

    public const ADSELECT_LAST_EXPORTED_CASE_ID = 'adselect-export-case-id';

    public const LAST_UPDATED_IMPRESSION_ID = 'last-updated-impression-id';

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

    private const ADMIN_SETTINGS_DEFAULTS = [
        self::OPERATOR_TX_FEE => '',
        self::OPERATOR_RX_FEE => '',
        self::LICENCE_RX_FEE => '',
        self::HOT_WALLET_MIN_VALUE => '',
        self::HOT_WALLET_MAX_VALUE => '',
        self::COLD_WALLET_ADDRESS => '',
        self::COLD_WALLET_IS_ACTIVE => '',
        self::ADSERVER_NAME => '',
        self::TECHNICAL_EMAIL => '',
        self::SUPPORT_EMAIL => '',
        self::BONUS_NEW_USER_ENABLED => '',
        self::BONUS_NEW_USER_AMOUNT => '',
    ];

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $guarded = [];

    private static function whereKey(string $key): Builder
    {
        return self::where('key', $key);
    }

    private static function fetchByKey(string $key): ?self
    {
        return self::whereKey($key)->first();
    }

    private static function fetchByKeyOrFail(string $key): self
    {
        $object = self::fetchByKey($key);

        if ($object === null) {
            throw ConfigException::missingEntry($key);
        }

        return $object;
    }

    private static function fetchByKeyOrDefault(string $key, string $default = ''): string
    {
        $config = self::fetchByKey($key);

        if ($config === null) {
            return $default;
        }

        return $config->value;
    }

    public static function fetchDateTime(string $key, DateTime $default = null): DateTime
    {
        $dateString = self::fetchByKeyOrDefault($key);

        if ($dateString === '') {
            if ($default === null) {
                return new DateTime('@0');
            }

            return clone $default;
        }

        $object = DateTime::createFromFormat(DateTime::ATOM, $dateString);

        if ($object === false) {
            throw new RuntimeException(sprintf('Failed converting "%s" to DateTime', (string)$object));
        }

        return $object;
    }

    public static function fetchInt(string $key, int $default = 0): int
    {
        return (int)self::fetchByKeyOrDefault($key, (string)$default);
    }

    public static function fetchFloatOrFail(string $key, bool $allowLicenseKeys = false): float
    {
        $licenseKeys = [
            self::LICENCE_RX_FEE,
            self::LICENCE_TX_FEE,
        ];

        if (!$allowLicenseKeys && in_array($key, $licenseKeys, true)) {
            throw new RuntimeException(sprintf('These value %s need to be taken from a license reader', $key));
        }

        return (float)self::fetchByKeyOrFail($key)->value;
    }

    public static function fetchStringOrFail(string $key, bool $allowLicenseKeys = false): string
    {
        if (!$allowLicenseKeys && $key === self::LICENCE_ACCOUNT) {
            throw new RuntimeException(sprintf('This value %s needs to be taken from a license reader', $key));
        }

        return (string)self::fetchByKeyOrFail($key)->value;
    }

    private static function upsertByKey(string $key, string $value): void
    {
        $config = self::fetchByKey($key);

        if ($config === null) {
            $config = new self();
            $config->key = $key;
        }

        $config->value = $value;
        $config->save();
    }

    public static function upsertDateTime(string $key, DateTime $date): void
    {
        self::upsertByKey($key, $date->format(DateTime::ATOM));
    }

    public static function upsertInt(string $key, int $id): void
    {
        self::upsertByKey($key, (string)$id);
    }

    public static function isTrueOnly(string $key): bool
    {
        return self::fetchByKeyOrDefault($key) === '1';
    }

    public static function fetchAdminSettings(): array
    {
        $fetched = self::whereIn('key', array_keys(self::ADMIN_SETTINGS_DEFAULTS))
            ->get()
            ->pluck('value', 'key')
            ->toArray();

        return array_merge(self::ADMIN_SETTINGS_DEFAULTS, $fetched);
    }

    public static function updateAdminSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            self::upsertByKey($key, $value);
        }
    }
}
