<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

use Adshares\Adserver\Utilities\ConfigTypes;
use Adshares\Adserver\Utilities\DomainReader;
use Adshares\Common\Application\Model\Currency;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Adshares\Config\RegistrationMode;
use Adshares\Config\UserRole;
use DateTime;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;
use Throwable;

/**
 * @property string key
 * @property string value
 * @mixin Builder
 */
class Config extends Model
{
    use HasFactory;

    public const ADPANEL_URL = 'adpanel-url';
    public const ADPAY_BID_STRATEGY_EXPORT_TIME = 'adpay-bid-strategy-export';
    public const ADPAY_CAMPAIGN_EXPORT_TIME = 'adpay-campaign-export';
    public const ADPAY_LAST_EXPORTED_CONVERSION_TIME = 'adpay-last-conversion-time';
    public const ADPAY_LAST_EXPORTED_EVENT_TIME = 'adpay-last-event-time';
    public const ADPAY_URL = 'adpay-url';
    public const ADS_LOG_START = 'ads-log-start';
    public const ADS_OPERATOR_SERVER_URL = 'ads-operator-server-url';
    public const ADS_RPC_URL = 'ads-rpc-url';
    public const ADS_TXT_CHECK_DEMAND_ENABLED = 'ads-txt-check-demand-enabled';
    public const ADS_TXT_CHECK_SUPPLY_ENABLED = 'ads-txt-check-supply-enabled';
    public const ADS_TXT_DOMAIN = 'ads-txt-domain';
    public const ADSELECT_INVENTORY_EXPORT_TIME = 'adselect-inventory-export';
    public const ADSELECT_URL = 'adselect-url';
    public const ADSERVER_NAME = 'adserver-name';
    public const ADSHARES_ADDRESS = 'adshares-address';
    public const ADSHARES_LICENSE_KEY = 'adshares-license-key';
    public const ADSHARES_LICENSE_SERVER_URL = 'adshares-license-server-url';
    public const ADSHARES_NODE_HOST = 'adshares-node-host';
    public const ADSHARES_NODE_PORT = 'adshares-node-port';
    public const ADSHARES_SECRET = 'adshares-secret';
    public const ADUSER_BASE_URL = 'aduser-base-url';
    public const ADUSER_INFO_URL = 'aduser-info-url';
    public const ADUSER_INTERNAL_URL = 'aduser-internal-url';
    public const ADUSER_SERVE_SUBDOMAIN = 'aduser-serve-subdomain';
    public const ADVERTISER_APPLY_FORM_URL = 'advertiser-apply-form-url';
    public const ALLOW_ZONE_IN_IFRAME = 'allow-zone-in-iframe';
    public const AUTO_CONFIRMATION_ENABLED = 'auto-confirmation-enabled';
    public const AUTO_REGISTRATION_ENABLED = 'auto-registration-enabled';
    public const AUTO_WITHDRAWAL_LIMIT_ADS = 'auto-withdrawal-limit-ads';
    public const AUTO_WITHDRAWAL_LIMIT_BSC = 'auto-withdrawal-limit-bsc';
    public const AUTO_WITHDRAWAL_LIMIT_BTC = 'auto-withdrawal-limit-btc';
    public const AUTO_WITHDRAWAL_LIMIT_ETH = 'auto-withdrawal-limit-eth';
    public const BANNER_FORCE_HTTPS = 'banner-force-https';
    public const BANNER_ROTATE_INTERVAL = 'banner-rotate-interval';
    public const BTC_WITHDRAW = 'btc-withdraw';
    public const BTC_WITHDRAW_FEE = 'btc-withdraw-fee';
    public const BTC_WITHDRAW_MAX_AMOUNT = 'btc-withdraw-max-amount';
    public const BTC_WITHDRAW_MIN_AMOUNT = 'btc-withdraw-min-amount';
    public const CAMPAIGN_MIN_BUDGET = 'campaign-min-budget';
    public const CAMPAIGN_MIN_CPA = 'campaign-min-cpa';
    public const CAMPAIGN_MIN_CPM = 'campaign-min-cpm';
    public const CAMPAIGN_TARGETING_EXCLUDE = 'campaign-targeting-exclude';
    public const CAMPAIGN_TARGETING_REQUIRE = 'campaign-targeting-require';
    public const CDN_PROVIDER = 'cdn-provider';
    public const CHECK_ZONE_DOMAIN = 'check-zone-domain';
    public const CLASSIFIER_EXTERNAL_API_KEY_NAME = 'classifier-external-api-key-name';
    public const CLASSIFIER_EXTERNAL_API_KEY_SECRET = 'classifier-external-api-key-secret';
    public const CLASSIFIER_EXTERNAL_BASE_URL = 'classifier-external-base-url';
    public const CLASSIFIER_EXTERNAL_NAME = 'classifier-external-name';
    public const CLASSIFIER_EXTERNAL_PUBLIC_KEY = 'classifier-external-public-key';
    public const COLD_WALLET_ADDRESS = 'cold-wallet-address';
    public const COLD_WALLET_IS_ACTIVE = 'cold-wallet-is-active';
    public const CRM_MAIL_ADDRESS_ON_CAMPAIGN_CREATED = 'crm-mail-address-on-campaign-created';
    public const CRM_MAIL_ADDRESS_ON_SITE_ADDED = 'crm-mail-address-on-site-added';
    public const CRM_MAIL_ADDRESS_ON_USER_REGISTERED = 'crm-mail-address-on-user-registered';
    public const CURRENCY = 'currency';
    public const DEFAULT_USER_ROLES = 'default-user-roles';
    public const DISPLAY_CURRENCY = 'display-currency';
    public const EMAIL_VERIFICATION_REQUIRED = 'email-verification-required';
    public const EXCHANGE_API_KEY = 'exchange-api-key';
    public const EXCHANGE_API_SECRET = 'exchange-api-secret';
    public const EXCHANGE_API_URL = 'exchange-api-url';
    public const EXCHANGE_CURRENCIES = 'exchange-currencies';
    public const FIAT_DEPOSIT_MAX_AMOUNT = 'fiat-deposit-max-amount';
    public const FIAT_DEPOSIT_MIN_AMOUNT = 'fiat-deposit-min-amount';
    public const HOT_WALLET_MAX_VALUE = 'hotwallet-max-value';
    public const HOT_WALLET_MIN_VALUE = 'hotwallet-min-value';
    public const HOURS_UNTIL_INACTIVE_HOST_REMOVAL = 'hours-until-inactive-host-removal';
    public const INVENTORY_EXPORT_WHITELIST = 'inventory-export-whitelist';
    public const INVENTORY_FAILED_CONNECTION_LIMIT = 'inventory-failed-connection-limit';
    public const INVENTORY_IMPORT_WHITELIST = 'inventory-import-whitelist';
    public const INVENTORY_WHITELIST = 'inventory-whitelist';
    public const INVOICE_COMPANY_ADDRESS = 'invoice-company-address';
    public const INVOICE_COMPANY_BANK_ACCOUNTS = 'invoice-company-bank-accounts';
    public const INVOICE_COMPANY_CITY = 'invoice-company-city';
    public const INVOICE_COMPANY_COUNTRY = 'invoice-company-country';
    public const INVOICE_COMPANY_NAME = 'invoice-company-name';
    public const INVOICE_COMPANY_POSTAL_CODE = 'invoice-company-postal-code';
    public const INVOICE_COMPANY_VAT_ID = 'invoice-company-vat-id';
    public const INVOICE_CURRENCIES = 'invoice-currencies';
    public const INVOICE_ENABLED = 'invoice-enabled';
    public const INVOICE_NUMBER_FORMAT = 'invoice-number-format';
    public const LANDING_URL = 'landing-url';
    public const LAST_UPDATED_IMPRESSION_ID = 'last-updated-impression-id';
    public const MAIL_FROM_ADDRESS = 'mail-from-address';
    public const MAIL_FROM_NAME = 'mail-from-name';
    public const MAIL_MAILER = 'mail-mailer';
    public const MAIL_SMTP_ENCRYPTION = 'mail-smtp-encryption';
    public const MAIL_SMTP_HOST = 'mail-smtp-host';
    public const MAIL_SMTP_PASSWORD = 'mail-smtp-password';
    public const MAIL_SMTP_PORT = 'mail-smtp-port';
    public const MAIL_SMTP_USERNAME = 'mail-smtp-username';
    public const MAIN_JS_BASE_URL = 'main-js-base-url';
    public const MAIN_JS_TLD = 'main-js-tld';
    public const MAX_INVALID_LOGIN_ATTEMPTS = 'max-invalid-login-attempts';
    public const MAX_PAGE_ZONES = 'max-page-zones';
    public const NETWORK_DATA_CACHE_TTL = 'network-data-cache-ttl';
    public const NOW_PAYMENTS_API_KEY = 'now-payments-api-key';
    public const NOW_PAYMENTS_CURRENCY = 'now-payments-currency';
    public const NOW_PAYMENTS_EXCHANGE = 'now-payments-exchange';
    public const NOW_PAYMENTS_FEE = 'now-payments-fee';
    public const NOW_PAYMENTS_IPN_SECRET = 'now-payments-ipn-secret';
    public const NOW_PAYMENTS_MAX_AMOUNT = 'now-payments-max-amount';
    public const NOW_PAYMENTS_MIN_AMOUNT = 'now-payments-min-amount';
    public const OPERATOR_RX_FEE = 'payment-rx-fee';
    public const OPERATOR_TX_FEE = 'payment-tx-fee';
    public const OPERATOR_WALLET_EMAIL_LAST_TIME = 'operator-wallet-transfer-email-time';
    public const PANEL_PLACEHOLDER_NOTIFICATION_TIME = 'panel-placeholder-notification-time';
    public const PANEL_PLACEHOLDER_UPDATE_TIME = 'panel-placeholder-update-time';
    public const PUBLISHER_APPLY_FORM_URL = 'publisher-apply-form-url';
    public const REFERRAL_REFUND_COMMISSION = 'referral-refund-commission';
    public const REFERRAL_REFUND_ENABLED = 'referral-refund-enabled';
    public const REGISTRATION_MODE = 'registration-mode';
    public const SERVE_BASE_URL = 'serve-base-url';
    public const SITE_ACCEPT_BANNERS_MANUALLY = 'site-accept-banners-manually';
    public const SITE_APPROVAL_REQUIRED = 'site-approval-required';
    public const SITE_CLASSIFIER_LOCAL_BANNERS = 'site-classifier-local-banners';
    public const SITE_FILTERING_EXCLUDE = 'site-filtering-exclude';
    public const SITE_FILTERING_EXCLUDE_ON_AUTO_CREATE = 'site-filtering-exclude-on-auto-create';
    public const SITE_FILTERING_REQUIRE = 'site-filtering-require';
    public const SITE_FILTERING_REQUIRE_ON_AUTO_CREATE = 'site-filtering-require-on-auto-create';
    public const SITE_VERIFICATION_NOTIFICATION_TIME_THRESHOLD = 'site-verification-time-threshold';
    public const SKYNET_API_KEY = 'skynet-api-key';
    public const SKYNET_API_URL = 'skynet-api-url';
    public const SKYNET_CDN_URL = 'skynet-cdn-url';
    public const SUPPORT_CHAT = 'support-chat';
    public const SUPPORT_EMAIL = 'support-email';
    public const SUPPORT_TELEGRAM = 'support-telegram';
    public const TECHNICAL_EMAIL = 'technical-email';
    public const UPLOAD_LIMIT_DIRECT_LINK = 'upload-limit-direct-link';
    public const UPLOAD_LIMIT_IMAGE = 'upload-limit-image';
    public const UPLOAD_LIMIT_MODEL = 'upload-limit-model';
    public const UPLOAD_LIMIT_VIDEO = 'upload-limit-video';
    public const UPLOAD_LIMIT_ZIP = 'upload-limit-zip';
    public const URL = 'url';

    /** @deprecated default uuid is stored in DB in bid_strategy table */
    public const BID_STRATEGY_UUID_DEFAULT = 'bid-strategy-uuid-default';
    /** @deprecated account ID should be read from {@see LicenseReader} */
    public const LICENCE_ACCOUNT = 'licence-account';
    /** @deprecated fee should be read from {@see LicenseReader} */
    public const LICENCE_RX_FEE = 'licence-rx-fee';
    /** @deprecated fee should be read from {@see LicenseReader} */
    public const LICENCE_TX_FEE = 'licence-tx-fee';

    public const ALLOWED_CLASSIFIER_LOCAL_BANNERS_OPTIONS = [
        self::CLASSIFIER_LOCAL_BANNERS_ALL_BY_DEFAULT,
        self::CLASSIFIER_LOCAL_BANNERS_LOCAL_BY_DEFAULT,
        self::CLASSIFIER_LOCAL_BANNERS_LOCAL_ONLY,
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

    private const TYPE_CONVERSIONS = [
        self::ADS_TXT_CHECK_DEMAND_ENABLED => ConfigTypes::Bool,
        self::ADS_TXT_CHECK_SUPPLY_ENABLED => ConfigTypes::Bool,
        self::ADSHARES_NODE_PORT => ConfigTypes::Integer,
        self::ALLOW_ZONE_IN_IFRAME => ConfigTypes::Bool,
        self::AUTO_CONFIRMATION_ENABLED => ConfigTypes::Bool,
        self::AUTO_REGISTRATION_ENABLED => ConfigTypes::Bool,
        self::AUTO_WITHDRAWAL_LIMIT_ADS => ConfigTypes::Integer,
        self::AUTO_WITHDRAWAL_LIMIT_BSC => ConfigTypes::Integer,
        self::AUTO_WITHDRAWAL_LIMIT_BTC => ConfigTypes::Integer,
        self::AUTO_WITHDRAWAL_LIMIT_ETH => ConfigTypes::Integer,
        self::BANNER_FORCE_HTTPS => ConfigTypes::Bool,
        self::BANNER_ROTATE_INTERVAL => ConfigTypes::Integer,
        self::BTC_WITHDRAW => ConfigTypes::Bool,
        self::BTC_WITHDRAW_FEE => ConfigTypes::Float,
        self::BTC_WITHDRAW_MAX_AMOUNT => ConfigTypes::Integer,
        self::BTC_WITHDRAW_MIN_AMOUNT => ConfigTypes::Integer,
        self::CAMPAIGN_MIN_BUDGET => ConfigTypes::Integer,
        self::CAMPAIGN_MIN_CPA => ConfigTypes::Integer,
        self::CAMPAIGN_MIN_CPM => ConfigTypes::Integer,
        self::CHECK_ZONE_DOMAIN => ConfigTypes::Bool,
        self::COLD_WALLET_IS_ACTIVE => ConfigTypes::Bool,
        self::DEFAULT_USER_ROLES => ConfigTypes::Array,
        self::EMAIL_VERIFICATION_REQUIRED => ConfigTypes::Bool,
        self::EXCHANGE_CURRENCIES => ConfigTypes::Array,
        self::FIAT_DEPOSIT_MAX_AMOUNT => ConfigTypes::Integer,
        self::FIAT_DEPOSIT_MIN_AMOUNT => ConfigTypes::Integer,
        self::HOT_WALLET_MAX_VALUE => ConfigTypes::Integer,
        self::HOT_WALLET_MIN_VALUE => ConfigTypes::Integer,
        self::HOURS_UNTIL_INACTIVE_HOST_REMOVAL => ConfigTypes::Integer,
        self::INVENTORY_EXPORT_WHITELIST => ConfigTypes::Array,
        self::INVENTORY_FAILED_CONNECTION_LIMIT => ConfigTypes::Integer,
        self::INVENTORY_IMPORT_WHITELIST => ConfigTypes::Array,
        self::INVENTORY_WHITELIST => ConfigTypes::Array,
        self::INVOICE_COMPANY_BANK_ACCOUNTS => ConfigTypes::Json,
        self::INVOICE_CURRENCIES => ConfigTypes::Array,
        self::INVOICE_ENABLED => ConfigTypes::Bool,
        self::MAX_INVALID_LOGIN_ATTEMPTS => ConfigTypes::Integer,
        self::MAX_PAGE_ZONES => ConfigTypes::Integer,
        self::MAIL_SMTP_PORT => ConfigTypes::Integer,
        self::NETWORK_DATA_CACHE_TTL => ConfigTypes::Integer,
        self::NOW_PAYMENTS_EXCHANGE => ConfigTypes::Bool,
        self::NOW_PAYMENTS_FEE => ConfigTypes::Float,
        self::NOW_PAYMENTS_MAX_AMOUNT => ConfigTypes::Integer,
        self::NOW_PAYMENTS_MIN_AMOUNT => ConfigTypes::Integer,
        self::OPERATOR_TX_FEE => ConfigTypes::Float,
        self::OPERATOR_RX_FEE => ConfigTypes::Float,
        self::REFERRAL_REFUND_COMMISSION => ConfigTypes::Float,
        self::REFERRAL_REFUND_ENABLED => ConfigTypes::Bool,
        self::SITE_ACCEPT_BANNERS_MANUALLY => ConfigTypes::Bool,
        self::SITE_APPROVAL_REQUIRED => ConfigTypes::Array,
        self::SITE_FILTERING_EXCLUDE => ConfigTypes::Json,
        self::SITE_FILTERING_EXCLUDE_ON_AUTO_CREATE => ConfigTypes::Json,
        self::SITE_FILTERING_REQUIRE => ConfigTypes::Json,
        self::SITE_FILTERING_REQUIRE_ON_AUTO_CREATE => ConfigTypes::Json,
        self::UPLOAD_LIMIT_DIRECT_LINK => ConfigTypes::Integer,
        self::UPLOAD_LIMIT_IMAGE => ConfigTypes::Integer,
        self::UPLOAD_LIMIT_MODEL => ConfigTypes::Integer,
        self::UPLOAD_LIMIT_VIDEO => ConfigTypes::Integer,
        self::UPLOAD_LIMIT_ZIP => ConfigTypes::Integer,
    ];

    private const SECRETS = [
        self::ADSHARES_LICENSE_KEY,
        self::ADSHARES_SECRET,
        self::CLASSIFIER_EXTERNAL_API_KEY_SECRET,
        self::EXCHANGE_API_SECRET,
        self::MAIL_SMTP_PASSWORD,
        self::NOW_PAYMENTS_IPN_SECRET,
        self::SKYNET_API_KEY,
    ];

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $guarded = [];

    private static function whereKey(string $key): Builder
    {
        return self::query()->where('key', $key);
    }

    private static function fetchByKey(string $key): ?self
    {
        return self::whereKey($key)->first();
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

    public static function fetchAdminSettings(bool $withSecrets = false): array
    {
        try {
            $adminSettings = Cache::remember('config.admin', 10 * 60, function () {
                $fetched = self::all()
                    ->pluck('value', 'key')
                    ->toArray();
                foreach ($fetched as $key => $value) {
                    $fetched[$key] = self::mapValueType($key, $value);
                }
                return array_merge(self::getDefaultAdminSettings($fetched), $fetched);
            });
        } catch (PDOException $exception) {
            Log::error(sprintf('Fetching admin settings from DB failed. %s', $exception->getMessage()));
            $adminSettings = self::getDefaultAdminSettings();
        }

        if (!$withSecrets) {
            foreach (self::SECRETS as $secretKey) {
                unset($adminSettings[$secretKey]);
            }
        }

        return $adminSettings;
    }

    private static function mapValueType(string $key, ?string $value): string|array|int|null|float|bool
    {
        if (null === $value) {
            return null;
        }

        return match (self::TYPE_CONVERSIONS[$key] ?? ConfigTypes::String) {
            ConfigTypes::Array => array_filter(explode(',', $value)),
            ConfigTypes::Bool => '1' === $value,
            ConfigTypes::Float => (float)$value,
            ConfigTypes::Integer => (int)$value,
            ConfigTypes::Json => json_decode($value, true),
            default => $value,
        };
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

    private static function getDefaultAdminSettings(array $fetched = []): array
    {
        $adpanelUrlDefault = 'http://localhost:8080';
        return [
            self::ADPANEL_URL => $adpanelUrlDefault,
            self::ADPAY_URL => 'http://localhost:8012',
            self::ADS_OPERATOR_SERVER_URL => 'https://ads-operator.adshares.net',
            self::ADS_RPC_URL => 'https://rpc.adshares.net',
            self::ADS_TXT_CHECK_DEMAND_ENABLED => false,
            self::ADS_TXT_CHECK_SUPPLY_ENABLED => false,
            self::ADS_TXT_DOMAIN => empty($fetched[self::URL]) ? '' : DomainReader::domain($fetched[self::URL]),
            self::ADSELECT_URL => 'http://localhost:8011',
            self::ADSERVER_NAME => 'AdServer',
            self::ADSHARES_ADDRESS => null,
            self::ADSHARES_LICENSE_KEY => '',
            self::ADSHARES_LICENSE_SERVER_URL => 'https://account.adshares.pl/',
            self::ADSHARES_NODE_HOST => '',
            self::ADSHARES_NODE_PORT => 6511,
            self::ADSHARES_SECRET => null,
            self::ADUSER_BASE_URL => '',
            self::ADUSER_INFO_URL =>
                isset($fetched[self::ADUSER_BASE_URL])
                    ? ($fetched[self::ADUSER_BASE_URL] . '/panel.html?rated=1&url={domain}') : '',
            self::ADUSER_INTERNAL_URL => $fetched[self::ADUSER_BASE_URL] ?? '',
            self::ADUSER_SERVE_SUBDOMAIN => '',
            self::ADVERTISER_APPLY_FORM_URL => null,
            self::ALLOW_ZONE_IN_IFRAME => true,
            self::AUTO_CONFIRMATION_ENABLED => false,
            self::AUTO_REGISTRATION_ENABLED => false,
            self::AUTO_WITHDRAWAL_LIMIT_ADS => 1_000_000_00,
            self::AUTO_WITHDRAWAL_LIMIT_BSC => 1_000_000_000_00,
            self::AUTO_WITHDRAWAL_LIMIT_BTC => 1_000_000_000_000_00,
            self::AUTO_WITHDRAWAL_LIMIT_ETH => 1_000_000_000_000_00,
            self::BANNER_FORCE_HTTPS => true,
            self::BANNER_ROTATE_INTERVAL => 86400,
            self::BTC_WITHDRAW => false,
            self::BTC_WITHDRAW_FEE => 0.05,
            self::BTC_WITHDRAW_MAX_AMOUNT => 1000000000000000,
            self::BTC_WITHDRAW_MIN_AMOUNT => 10000000000000,
            self::CAMPAIGN_MIN_BUDGET => 5000000000,
            self::CAMPAIGN_MIN_CPA => 1000000000,
            self::CAMPAIGN_MIN_CPM => 5000000000,
            self::CAMPAIGN_TARGETING_EXCLUDE => '',
            self::CAMPAIGN_TARGETING_REQUIRE => '',
            self::CDN_PROVIDER => '',
            self::CHECK_ZONE_DOMAIN => true,
            self::CLASSIFIER_EXTERNAL_API_KEY_NAME => '',
            self::CLASSIFIER_EXTERNAL_API_KEY_SECRET => '',
            self::CLASSIFIER_EXTERNAL_BASE_URL => 'https://adclassify.adshares.net',
            self::CLASSIFIER_EXTERNAL_NAME => '0001000000081a67',
            self::CLASSIFIER_EXTERNAL_PUBLIC_KEY => 'FE736A82F91247B022953A58744EAEA18C477468831E680EEDFB49A29F6F7088',
            self::COLD_WALLET_ADDRESS => '',
            self::COLD_WALLET_IS_ACTIVE => false,
            self::CRM_MAIL_ADDRESS_ON_CAMPAIGN_CREATED => '',
            self::CRM_MAIL_ADDRESS_ON_SITE_ADDED => '',
            self::CRM_MAIL_ADDRESS_ON_USER_REGISTERED => '',
            self::CURRENCY => Currency::ADS->value,
            self::DEFAULT_USER_ROLES => [UserRole::ADVERTISER, UserRole::PUBLISHER],
            self::DISPLAY_CURRENCY => Currency::USD->value,
            self::EMAIL_VERIFICATION_REQUIRED => true,
            self::EXCHANGE_API_KEY => '',
            self::EXCHANGE_API_SECRET => '',
            self::EXCHANGE_API_URL => '',
            self::EXCHANGE_CURRENCIES => ['USD', 'BTC'],
            self::FIAT_DEPOSIT_MAX_AMOUNT => 100000,
            self::FIAT_DEPOSIT_MIN_AMOUNT => 2000,
            self::HOT_WALLET_MAX_VALUE => 10_000_000_000_000_00,
            self::HOT_WALLET_MIN_VALUE => 1_000_000_000_000_00,
            self::HOURS_UNTIL_INACTIVE_HOST_REMOVAL => 7 * 24,
            self::INVENTORY_EXPORT_WHITELIST => $fetched[self::INVENTORY_WHITELIST] ?? [],
            self::INVENTORY_FAILED_CONNECTION_LIMIT => 10,
            self::INVENTORY_IMPORT_WHITELIST => $fetched[self::INVENTORY_WHITELIST] ?? [],
            self::INVENTORY_WHITELIST => [],
            self::INVOICE_COMPANY_ADDRESS => '',
            self::INVOICE_COMPANY_BANK_ACCOUNTS => '',
            self::INVOICE_COMPANY_CITY => '',
            self::INVOICE_COMPANY_COUNTRY => '',
            self::INVOICE_COMPANY_NAME => '',
            self::INVOICE_COMPANY_POSTAL_CODE => '',
            self::INVOICE_COMPANY_VAT_ID => '',
            self::INVOICE_CURRENCIES => [],
            self::INVOICE_ENABLED => false,
            self::INVOICE_NUMBER_FORMAT => 'INV NNNN/MM/YYYY',
            self::LANDING_URL => $fetched[self::ADPANEL_URL] ?? $adpanelUrlDefault,
            self::MAIL_FROM_ADDRESS => $fetched[self::SUPPORT_EMAIL] ?? '',
            self::MAIL_FROM_NAME => 'Adshares AdServer',
            self::MAIL_MAILER => 'smtp',
            self::MAIL_SMTP_ENCRYPTION => 'tls',
            self::MAIL_SMTP_HOST => 'localhost',
            self::MAIL_SMTP_PASSWORD => '',
            self::MAIL_SMTP_PORT => 587,
            self::MAIL_SMTP_USERNAME => '',
            self::MAIN_JS_BASE_URL => $fetched[self::URL] ?? '',
            self::MAIN_JS_TLD => '',
            self::MAX_INVALID_LOGIN_ATTEMPTS => 5,
            self::MAX_PAGE_ZONES => 4,
            self::NETWORK_DATA_CACHE_TTL => 60,
            self::NOW_PAYMENTS_API_KEY => '',
            self::NOW_PAYMENTS_CURRENCY => 'USD',
            self::NOW_PAYMENTS_EXCHANGE => false,
            self::NOW_PAYMENTS_FEE => 0.05,
            self::NOW_PAYMENTS_IPN_SECRET => '',
            self::NOW_PAYMENTS_MAX_AMOUNT => 1000,
            self::NOW_PAYMENTS_MIN_AMOUNT => 25,
            self::OPERATOR_RX_FEE => 0.01,
            self::OPERATOR_TX_FEE => 0.01,
            self::PUBLISHER_APPLY_FORM_URL => null,
            self::REFERRAL_REFUND_COMMISSION => 0,
            self::REFERRAL_REFUND_ENABLED => false,
            self::REGISTRATION_MODE => RegistrationMode::PRIVATE,
            self::SERVE_BASE_URL => $fetched[self::URL] ?? '',
            self::SITE_ACCEPT_BANNERS_MANUALLY => false,
            self::SITE_APPROVAL_REQUIRED => [],
            self::SITE_CLASSIFIER_LOCAL_BANNERS => self::CLASSIFIER_LOCAL_BANNERS_ALL_BY_DEFAULT,
            self::SITE_FILTERING_EXCLUDE => [],
            self::SITE_FILTERING_EXCLUDE_ON_AUTO_CREATE => [],
            self::SITE_FILTERING_REQUIRE => [],
            self::SITE_FILTERING_REQUIRE_ON_AUTO_CREATE => [],
            self::SKYNET_API_KEY => '',
            self::SKYNET_API_URL => 'https://siasky.net',
            self::SKYNET_CDN_URL => '',
            self::SUPPORT_CHAT => null,
            self::SUPPORT_EMAIL => 'mail@example.com',
            self::SUPPORT_TELEGRAM => null,
            self::TECHNICAL_EMAIL => 'mail@example.com',
            self::UPLOAD_LIMIT_DIRECT_LINK => 1024,
            self::UPLOAD_LIMIT_IMAGE => 512 * 1024,
            self::UPLOAD_LIMIT_MODEL => 1024 * 1024,
            self::UPLOAD_LIMIT_VIDEO => 1024 * 1024,
            self::UPLOAD_LIMIT_ZIP => 512 * 1024,
            self::URL => '',
        ];
    }
}
