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

namespace Adshares\Adserver\Models;

use Adshares\Common\Exception\RuntimeException;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Adshares\Config\RegistrationMode;
use DateTime;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * @property string key
 * @property string value
 * @mixin Builder
 */
class Config extends Model
{
    use HasFactory;

    public const ADS_LOG_START = 'ads-log-start';
    public const ADSHARES_ADDRESS = 'adshares-address';
    public const ADSHARES_LICENSE_ID = 'adshares-license-id';
    public const ADSHARES_LICENSE_KEY = 'adshares-license-key';
    public const ADSHARES_LICENSE_SERVER_URL = 'adshares-license-server-url';
    public const ADSHARES_NODE_HOST = 'adshares-node-host';
    public const ADSHARES_NODE_PORT = 'adshares-node-port';
    public const ADSHARES_SECRET = 'adshares-secret';
    public const ALLOW_ZONE_IN_IFRAME = 'allow_zone-in-iframe';
    public const AUTO_WITHDRAWAL_LIMIT_ADS = 'auto-withdrawal-limit-ads';
    public const AUTO_WITHDRAWAL_LIMIT_BSC = 'auto-withdrawal-limit-bsc';
    public const AUTO_WITHDRAWAL_LIMIT_BTC = 'auto-withdrawal-limit-btc';
    public const AUTO_WITHDRAWAL_LIMIT_ETH = 'auto-withdrawal-limit-eth';
    public const BANNER_FORCE_HTTPS = 'banner-force-https';
    public const BTC_WITHDRAW = 'btc-withdraw';
    public const BTC_WITHDRAW_FEE = 'btc-withdraw-fee';
    public const BTC_WITHDRAW_MAX_AMOUNT = 'btc-withdraw-max-amount';
    public const BTC_WITHDRAW_MIN_AMOUNT = 'btc-withdraw-min-amount';
    public const CAMPAIGN_TARGETING_EXCLUDE = 'campaign-targeting-exclude';
    public const CAMPAIGN_TARGETING_REQUIRE = 'campaign-targeting-require';
    public const CDN_PROVIDER = 'cdn-provider';
    public const CHECK_ZONE_DOMAIN = 'check-zone-domain';
    public const CLASSIFIER_EXTERNAL_API_KEY_NAME = 'classifier-external-api-key-name';
    public const CLASSIFIER_EXTERNAL_API_KEY_SECRET = 'classifier-external-api-key-secret';
    public const CLASSIFIER_EXTERNAL_BASE_URL = 'classifier-external-base-url';
    public const CLASSIFIER_EXTERNAL_NAME = 'classifier-external-name';
    public const CLASSIFIER_EXTERNAL_PUBLIC_KEY = 'classifier-external-public-key';
    public const CRM_MAIL_ADDRESS_ON_CAMPAIGN_CREATED = 'crm-mail-address-on-campaign-created';
    public const CRM_MAIL_ADDRESS_ON_SITE_ADDED = 'crm-mail-address-on-site-added';
    public const CRM_MAIL_ADDRESS_ON_USER_REGISTERED = 'crm-mail-address-on-user-registered';
    public const EXCHANGE_API_KEY = 'exchange-api-key';
    public const EXCHANGE_API_SECRET = 'exchange-api-secret';
    public const EXCHANGE_API_URL = 'exchange-api-url';
    public const EXCHANGE_CURRENCIES = 'exchange-currencies';
    public const FIAT_DEPOSIT_MAX_AMOUNT = 'fiat-deposit-max-amount';
    public const FIAT_DEPOSIT_MIN_AMOUNT = 'fiat-deposit-min-amount';
    public const INVENTORY_EXPORT_WHITELIST = 'inventory-export-whitelist';
    public const INVENTORY_IMPORT_WHITELIST = 'inventory-import-whitelist';
    public const INVENTORY_WHITELIST = 'inventory-whitelist';
    public const MAX_PAGE_ZONES = 'max-page-zones';
    public const NETWORK_DATA_CACHE_TTL = 'network_data_cache-ttl';
    public const NOW_PAYMENTS_API_KEY = 'now-payments-api-key';
    public const NOW_PAYMENTS_CURRENCY = 'now-payments-currency';
    public const NOW_PAYMENTS_EXCHANGE = 'now-payments-exchange';
    public const NOW_PAYMENTS_FEE = 'now-payments-fee';
    public const NOW_PAYMENTS_IPN_SECRET = 'now-payments-ipn-secret';
    public const NOW_PAYMENTS_MAX_AMOUNT = 'now-payments-max-amount';
    public const NOW_PAYMENTS_MIN_AMOUNT = 'now-payments-min-amount';
    public const OPERATOR_TX_FEE = 'payment-tx-fee';
    public const OPERATOR_RX_FEE = 'payment-rx-fee';
    public const SITE_FILTERING_EXCLUDE = 'site-filtering-exclude';
    public const SITE_FILTERING_REQUIRE = 'site-filtering-require';
    public const SKYNET_API_KEY = 'skynet-api-key';
    public const SKYNET_API_URL = 'skynet-api-url';
    public const SKYNET_CDN_URL = 'skynet-cdn-url';
    public const UPLOAD_LIMIT_IMAGE = 'upload-limit-image';
    public const UPLOAD_LIMIT_MODEL = 'upload-limit-model';
    public const UPLOAD_LIMIT_VIDEO = 'upload-limit-video';
    public const UPLOAD_LIMIT_ZIP = 'upload-limit-zip';
    /** @deprecated fee should be read from {@see LicenseReader} */
    public const LICENCE_TX_FEE = 'licence-tx-fee';
    /** @deprecated fee should be read from {@see LicenseReader} */
    public const LICENCE_RX_FEE = 'licence-rx-fee';
    /** @deprecated account ID should be read from {@see LicenseReader} */
    public const LICENCE_ACCOUNT = 'licence-account';
    /** @deprecated default uuid is stored in DB in bid_strategy table */
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
    public const CAMPAIGN_MIN_BUDGET = 'campaign-min-budget';
    public const CAMPAIGN_MIN_CPA = 'campaign-min-cpa';
    public const CAMPAIGN_MIN_CPM = 'campaign-min-cpm';
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
    public const AUTO_REGISTRATION_ENABLED = 'auto-registration-enabled';
    public const AUTO_CONFIRMATION_ENABLED = 'auto-confirmation-enabled';
    public const EMAIL_VERIFICATION_REQUIRED = 'email-verification-required';
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
    public const SITE_ACCEPT_BANNERS_MANUALLY = 'site-accept-banners-manually';
    public const SITE_CLASSIFIER_LOCAL_BANNERS = 'site-classifier-local-banners';
    public const ALLOWED_CLASSIFIER_LOCAL_BANNERS_OPTIONS = [
        Config::CLASSIFIER_LOCAL_BANNERS_ALL_BY_DEFAULT,
        Config::CLASSIFIER_LOCAL_BANNERS_LOCAL_BY_DEFAULT,
        Config::CLASSIFIER_LOCAL_BANNERS_LOCAL_ONLY,
    ];
    public const CLASSIFIER_LOCAL_BANNERS_ALL_BY_DEFAULT = 'all-by-default';
    public const CLASSIFIER_LOCAL_BANNERS_LOCAL_BY_DEFAULT = 'local-by-default';
    public const CLASSIFIER_LOCAL_BANNERS_LOCAL_ONLY = 'local-only';

    private const TECHNICAL_SETTINGS = [
        self::ADS_LOG_START,
        self::ADSELECT_INVENTORY_EXPORT_TIME,
        self::ADPAY_BID_STRATEGY_EXPORT_TIME,
        self::ADPAY_CAMPAIGN_EXPORT_TIME,
        self::ADPAY_LAST_EXPORTED_CONVERSION_TIME,
        self::ADPAY_LAST_EXPORTED_EVENT_TIME,
        self::LAST_UPDATED_IMPRESSION_ID,
        self::OPERATOR_WALLET_EMAIL_LAST_TIME,
        self::PANEL_PLACEHOLDER_NOTIFICATION_TIME,
        self::PANEL_PLACEHOLDER_UPDATE_TIME,
        self::SITE_VERIFICATION_NOTIFICATION_TIME_THRESHOLD,
    ];
    private const SECRETS = [
        self::ADSHARES_LICENSE_KEY,
        self::ADSHARES_SECRET,
        self::CLASSIFIER_EXTERNAL_API_KEY_SECRET,
        self::EXCHANGE_API_SECRET,
        self::NOW_PAYMENTS_IPN_SECRET,
        self::SKYNET_API_KEY,
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
            throw new RuntimeException(sprintf('Failed converting "%s" to DateTime', $dateString));
        }

        return $object;
    }

    public static function fetchInt(string $key, int $default = 0): int
    {
        return (int)self::fetchByKeyOrDefault($key, (string)$default);
    }

    public static function fetchFloatOrFail(string $key): float
    {
        return (float)self::fetchByKeyOrFail($key)->value;
    }

    public static function fetchStringOrFail(string $key): string
    {
        return self::fetchByKeyOrFail($key)->value;
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
        return Cache::remember('config.admin', 10 * 60, function () {
            $fetched = self::all()
                ->pluck('value', 'key')
                ->toArray();
            return array_merge(self::getDefaultAdminSettings($fetched), $fetched);
        });
    }

    public static function updateAdminSettings(array $settings): void
    {
        DB::beginTransaction();
        try {
            foreach ($settings as $key => $value) {
                if (null === $value) {
                    Config::whereKey($key)->delete();
                    continue;
                }

                if (in_array($key, self::SECRETS, true)) {
                    $value = Crypt::encryptString($value);
                }
                self::upsertByKey($key, $value);
            }
            DB::commit();
        } catch (Throwable $exception) {
            Log::error(sprintf("Exception during administrator's settings update (%s)", $exception->getMessage()));
            DB::rollBack();
            throw $exception;
        }
        Cache::forget('config.admin');
    }

    private static function getDefaultAdminSettings(array $fetched): array
    {
        return [
            self::ADSERVER_NAME => '',
            self::ADSHARES_ADDRESS => '',
            self::ADSHARES_LICENSE_ID => '',
            self::ADSHARES_LICENSE_KEY => '',
            self::ADSHARES_LICENSE_SERVER_URL => 'https://account.adshares.pl/',
            self::ADSHARES_NODE_HOST => '',
            self::ADSHARES_NODE_PORT => '6511',
            self::ADSHARES_SECRET => null,
            self::ALLOW_ZONE_IN_IFRAME => '1',
            self::AUTO_CONFIRMATION_ENABLED => '0',
            self::AUTO_REGISTRATION_ENABLED => '0',
            self::AUTO_WITHDRAWAL_LIMIT_ADS => (string)1_000_000_00,
            self::AUTO_WITHDRAWAL_LIMIT_BSC => (string)1_000_000_000_00,
            self::AUTO_WITHDRAWAL_LIMIT_BTC => (string)1_000_000_000_000_00,
            self::AUTO_WITHDRAWAL_LIMIT_ETH => (string)1_000_000_000_000_00,
            self::BANNER_FORCE_HTTPS => '1',
            self::BTC_WITHDRAW => '0',
            self::BTC_WITHDRAW_FEE => '0.05',
            self::BTC_WITHDRAW_MAX_AMOUNT => '1000000000000000',
            self::BTC_WITHDRAW_MIN_AMOUNT => '10000000000000',
            self::CAMPAIGN_MIN_BUDGET => '5000000000',
            self::CAMPAIGN_MIN_CPA => '1000000000',
            self::CAMPAIGN_MIN_CPM => '5000000000',
            self::CAMPAIGN_TARGETING_EXCLUDE => '',
            self::CAMPAIGN_TARGETING_REQUIRE => '',
            self::CDN_PROVIDER => '',
            self::CHECK_ZONE_DOMAIN => '1',
            self::CLASSIFIER_EXTERNAL_API_KEY_NAME => '',
            self::CLASSIFIER_EXTERNAL_API_KEY_SECRET => '',
            self::CLASSIFIER_EXTERNAL_BASE_URL => 'https://adclassify.adshares.net',
            self::CLASSIFIER_EXTERNAL_NAME => '0001000000081a67',
            self::CLASSIFIER_EXTERNAL_PUBLIC_KEY => 'FE736A82F91247B022953A58744EAEA18C477468831E680EEDFB49A29F6F7088',
            self::COLD_WALLET_ADDRESS => '',
            self::COLD_WALLET_IS_ACTIVE => '0',
            self::CRM_MAIL_ADDRESS_ON_CAMPAIGN_CREATED => '',
            self::CRM_MAIL_ADDRESS_ON_SITE_ADDED => '',
            self::CRM_MAIL_ADDRESS_ON_USER_REGISTERED => '',
            self::EMAIL_VERIFICATION_REQUIRED => '0',
            self::EXCHANGE_API_KEY => '',
            self::EXCHANGE_API_SECRET => '',
            self::EXCHANGE_API_URL => '',
            self::EXCHANGE_CURRENCIES => 'USD,BTC',
            self::FIAT_DEPOSIT_MAX_AMOUNT => '100000',
            self::FIAT_DEPOSIT_MIN_AMOUNT => '2000',
            self::HOT_WALLET_MAX_VALUE => '50000000000000000',
            self::HOT_WALLET_MIN_VALUE => '2000000000000000',
            self::INVENTORY_EXPORT_WHITELIST => $fetched[self::INVENTORY_WHITELIST] ?? '',
            self::INVENTORY_IMPORT_WHITELIST => $fetched[self::INVENTORY_WHITELIST] ?? '',
            self::INVENTORY_WHITELIST => '',
            self::INVOICE_COMPANY_ADDRESS => '',
            self::INVOICE_COMPANY_BANK_ACCOUNTS => '',
            self::INVOICE_COMPANY_CITY => '',
            self::INVOICE_COMPANY_COUNTRY => '',
            self::INVOICE_COMPANY_NAME => '',
            self::INVOICE_COMPANY_POSTAL_CODE => '',
            self::INVOICE_COMPANY_VAT_ID => '',
            self::INVOICE_CURRENCIES => '',
            self::INVOICE_ENABLED => '0',
            self::INVOICE_NUMBER_FORMAT => '',
            self::MAX_PAGE_ZONES => '4',
            self::NETWORK_DATA_CACHE_TTL => '60',
            self::NOW_PAYMENTS_API_KEY => '',
            self::NOW_PAYMENTS_CURRENCY => 'USD',
            self::NOW_PAYMENTS_EXCHANGE => '0',
            self::NOW_PAYMENTS_FEE => '0.05',
            self::NOW_PAYMENTS_IPN_SECRET => '',
            self::NOW_PAYMENTS_MAX_AMOUNT => '1000',
            self::NOW_PAYMENTS_MIN_AMOUNT => '25',
            self::OPERATOR_RX_FEE => '0.01',
            self::OPERATOR_TX_FEE => '0.01',
            self::REFERRAL_REFUND_COMMISSION => '',
            self::REFERRAL_REFUND_ENABLED => '0',
            self::REGISTRATION_MODE => RegistrationMode::PRIVATE,
            self::SITE_ACCEPT_BANNERS_MANUALLY => '0',
            self::SITE_CLASSIFIER_LOCAL_BANNERS => self::CLASSIFIER_LOCAL_BANNERS_ALL_BY_DEFAULT,
            self::SITE_FILTERING_EXCLUDE => '',
            self::SITE_FILTERING_REQUIRE => '',
            self::SKYNET_API_KEY => '',
            self::SKYNET_API_URL => 'https://siasky.net',
            self::SKYNET_CDN_URL => '',
            self::SUPPORT_EMAIL => '',
            self::TECHNICAL_EMAIL => '',
            self::UPLOAD_LIMIT_IMAGE => (string)(512 * 1024),
            self::UPLOAD_LIMIT_MODEL => (string)(1024 * 1024),
            self::UPLOAD_LIMIT_VIDEO => (string)(1024 * 1024),
            self::UPLOAD_LIMIT_ZIP => (string)(512 * 1024),
        ];
    }
}
