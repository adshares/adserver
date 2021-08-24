<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Models;

use Adshares\Common\Exception\RuntimeException;
use DateTime;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

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
    public const BID_STRATEGY_UUID_DEFAULT = 'bid-strategy-uuid-default';
    public const ADPAY_BID_STRATEGY_EXPORT_TIME = 'adpay-bid-strategy-export';
    public const ADPAY_CAMPAIGN_EXPORT_TIME = 'adpay-campaign-export';
    public const ADPAY_LAST_EXPORTED_CONVERSION_TIME = 'adpay-last-conversion-time';
    public const ADPAY_LAST_EXPORTED_EVENT_TIME = 'adpay-last-event-time';
    public const ADSELECT_INVENTORY_EXPORT_TIME = 'adselect-inventory-export';
    public const LAST_UPDATED_IMPRESSION_ID = 'last-updated-impression-id';
    public const OPERATOR_WALLET_EMAIL_LAST_TIME = 'operator-wallet-transfer-email-time';
    public const HOT_WALLET_MIN_VALUE = 'hotwallet-min-value';
    public const HOT_WALLET_MAX_VALUE = 'hotwallet-max-value';
    public const COLD_WALLET_ADDRESS = 'cold-wallet-address';
    public const COLD_WALLET_IS_ACTIVE = 'cold-wallet-is-active';
    public const ADSERVER_NAME = 'adserver-name';
    public const TECHNICAL_EMAIL = 'technical-email';
    public const SUPPORT_EMAIL = 'support-email';
    public const PANEL_PLACEHOLDER_NOTIFICATION_TIME = 'panel-placeholder-notification-time';
    public const PANEL_PLACEHOLDER_UPDATE_TIME = 'panel-placeholder-update-time';
    public const SITE_VERIFICATION_NOTIFICATION_TIME_THRESHOLD = 'site-verification-time-threshold';
    public const REFERRAL_REFUND_ENABLED = 'referral-refund-enabled';
    public const REFERRAL_REFUND_COMMISSION = 'referral-refund-commission';
    public const REGISTRATION_MODE = 'registration-mode';
    public const AUTO_CONFIRMATION_ENABLED = 'auto-confirmation-enabled';
    public const INVOICE_ENABLED = 'invoice-enabled';
    public const INVOICE_CURRENCIES = 'invoice-currencies';
    public const INVOICE_NUMBER_FORMAT = 'invoice-number-format';
    public const INVOICE_COMPANY_NAME = 'invoice-company-name';
    public const INVOICE_COMPANY_ADDRESS = 'invoice-company-address';
    public const INVOICE_COMPANY_POSTAL_CODE = 'invoice-company-postal-code';
    public const INVOICE_COMPANY_CITY = 'invoice-company-city';
    public const INVOICE_COMPANY_COUNTRY = 'invoice-company-country';
    public const INVOICE_COMPANY_VAT_ID = 'invoice-company-vat-id';
    public const INVOICE_COMPANY_BANK_ACCOUNTS = 'invoice-company-bank-accounts';

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
        self::REFERRAL_REFUND_ENABLED => '',
        self::REFERRAL_REFUND_COMMISSION => '',
        self::REGISTRATION_MODE => '',
        self::AUTO_CONFIRMATION_ENABLED => '',
        self::INVOICE_ENABLED => '',
        self::INVOICE_CURRENCIES => '',
        self::INVOICE_NUMBER_FORMAT => '',
        self::INVOICE_COMPANY_NAME => '',
        self::INVOICE_COMPANY_ADDRESS => '',
        self::INVOICE_COMPANY_POSTAL_CODE => '',
        self::INVOICE_COMPANY_CITY => '',
        self::INVOICE_COMPANY_COUNTRY => '',
        self::INVOICE_COMPANY_VAT_ID => '',
        self::INVOICE_COMPANY_BANK_ACCOUNTS => '',
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

        $object = DateTime::createFromFormat(DateTimeInterface::ATOM, $dateString);

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

    public static function fetchJsonOrFail(string $key): array
    {
        return json_decode(self::fetchStringOrFail($key), true);
    }

    public static function upsertByKey(string $key, string $value): void
    {
        $config = self::fetchByKey($key);

        if ($config === null) {
            $config = new self();
            $config->key = $key;
        }

        $config->value = $value;
        $config->save();
    }

    public static function upsertDateTime(string $key, DateTimeInterface $value): void
    {
        self::upsertByKey($key, $value->format(DateTimeInterface::ATOM));
    }

    public static function upsertInt(string $key, int $value): void
    {
        self::upsertByKey($key, (string)$value);
    }

    public static function upsertFloat(string $key, float $value): void
    {
        self::upsertByKey($key, (string)$value);
    }

    public static function isTrueOnly(string $key): bool
    {
        return self::fetchByKeyOrDefault($key) === '1';
    }

    public static function fetchAdminSettings(): array
    {
        return Cache::remember('config.admin', 10, function () {
            $fetched = self::whereIn('key', array_keys(self::ADMIN_SETTINGS_DEFAULTS))
                ->get()
                ->pluck('value', 'key')
                ->toArray();
            return array_merge(self::ADMIN_SETTINGS_DEFAULTS, $fetched);
        });
    }

    public static function updateAdminSettings(array $settings): void
    {
        foreach ($settings as $key => $value) {
            self::upsertByKey($key, $value);
        }
        Cache::forget('config.admin');
    }
}
