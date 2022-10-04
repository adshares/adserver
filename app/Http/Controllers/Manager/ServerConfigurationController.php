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

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Ads\Util\AdsConverter;
use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Mail\PanelPlaceholdersChange;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\PanelPlaceholder;
use Adshares\Adserver\Models\SitesRejectedDomain;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Utilities\SiteValidator;
use Adshares\Common\Application\Model\Currency;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Config\RegistrationMode;
use Adshares\Config\UserRole;
use DateTimeImmutable;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

class ServerConfigurationController extends Controller
{
    private const ALLOWED_KEYS = [
        Config::ADPANEL_URL => 'nullable|url',
        Config::ADPAY_URL => 'nullable|url',
        Config::ADS_OPERATOR_SERVER_URL => 'nullable|url',
        Config::ADS_RPC_URL => 'nullable|url',
        Config::ADSELECT_URL => 'nullable|url',
        Config::ADSERVER_NAME => 'notEmpty',
        Config::ADSHARES_ADDRESS => 'accountId',
        Config::ADSHARES_LICENSE_KEY => 'nullable|licenseKey',
        Config::ADSHARES_LICENSE_SERVER_URL => 'nullable|url',
        Config::ADSHARES_NODE_HOST => 'host',
        Config::ADSHARES_NODE_PORT => 'nullable|port',
        Config::ADSHARES_SECRET => 'hex:64',
        Config::ADUSER_BASE_URL => 'nullable|url',
        Config::ADUSER_INFO_URL => 'nullable|url',
        Config::ADUSER_INTERNAL_URL => 'nullable|url',
        Config::ADUSER_SERVE_SUBDOMAIN => 'nullable|host',
        Config::ADVERTISER_APPLY_FORM_URL => 'nullable|url',
        Config::ALLOW_ZONE_IN_IFRAME => 'nullable|boolean',
        Config::AUTO_CONFIRMATION_ENABLED => 'nullable|boolean',
        Config::AUTO_REGISTRATION_ENABLED => 'nullable|boolean',
        Config::AUTO_WITHDRAWAL_LIMIT_ADS => 'nullable|positiveInteger',
        Config::AUTO_WITHDRAWAL_LIMIT_BSC => 'nullable|positiveInteger',
        Config::AUTO_WITHDRAWAL_LIMIT_BTC => 'nullable|positiveInteger',
        Config::AUTO_WITHDRAWAL_LIMIT_ETH => 'nullable|positiveInteger',
        Config::BANNER_FORCE_HTTPS => 'nullable|boolean',
        Config::BTC_WITHDRAW => 'nullable|boolean',
        Config::BTC_WITHDRAW_FEE => 'nullable|commission',
        Config::BTC_WITHDRAW_MAX_AMOUNT => 'nullable|clickAmount',
        Config::BTC_WITHDRAW_MIN_AMOUNT => 'nullable|clickAmount',
        Config::CAMPAIGN_MIN_BUDGET => 'nullable|clickAmount',
        Config::CAMPAIGN_MIN_CPA => 'nullable|clickAmount',
        Config::CAMPAIGN_MIN_CPM => 'nullable|clickAmount',
        Config::CAMPAIGN_TARGETING_EXCLUDE => 'nullable|json',
        Config::CAMPAIGN_TARGETING_REQUIRE => 'nullable|json',
        Config::CDN_PROVIDER => 'nullable',
        Config::CHECK_ZONE_DOMAIN => 'nullable|boolean',
        Config::CLASSIFIER_EXTERNAL_API_KEY_NAME => 'nullable|notEmpty',
        Config::CLASSIFIER_EXTERNAL_API_KEY_SECRET => 'nullable|notEmpty',
        Config::CLASSIFIER_EXTERNAL_BASE_URL => 'nullable|url',
        Config::CLASSIFIER_EXTERNAL_NAME => 'nullable|notEmpty',
        Config::CLASSIFIER_EXTERNAL_PUBLIC_KEY => 'nullable|hex:64',
        Config::COLD_WALLET_ADDRESS => 'accountId',
        Config::COLD_WALLET_IS_ACTIVE => 'nullable|boolean',
        Config::CRM_MAIL_ADDRESS_ON_CAMPAIGN_CREATED => 'nullable|email',
        Config::CRM_MAIL_ADDRESS_ON_SITE_ADDED => 'nullable|email',
        Config::CRM_MAIL_ADDRESS_ON_USER_REGISTERED => 'nullable|email',
        Config::CURRENCY => 'nullable|appCurrency',
        Config::DEFAULT_USER_ROLES => 'nullable|notEmpty|list:userRole',
        Config::EMAIL_VERIFICATION_REQUIRED => 'nullable|boolean',
        Config::EXCHANGE_API_KEY => 'nullable',
        Config::EXCHANGE_API_SECRET => 'nullable',
        Config::EXCHANGE_API_URL => 'nullable|url',
        Config::EXCHANGE_CURRENCIES => 'nullable|notEmpty|list:currency',
        Config::FIAT_DEPOSIT_MAX_AMOUNT => 'nullable|positiveInteger',
        Config::FIAT_DEPOSIT_MIN_AMOUNT => 'nullable|positiveInteger',
        Config::HOT_WALLET_MIN_VALUE => 'nullable|clickAmount',
        Config::HOT_WALLET_MAX_VALUE => 'nullable|clickAmount',
        Config::INVENTORY_EXPORT_WHITELIST => 'nullable|list:accountId',
        Config::INVENTORY_FAILED_CONNECTION_LIMIT => 'nullable|positiveInteger',
        Config::INVENTORY_IMPORT_WHITELIST => 'nullable|list:accountId',
        Config::INVENTORY_WHITELIST => 'nullable|list:accountId',
        Config::INVOICE_COMPANY_ADDRESS => 'nullable|notEmpty',
        Config::INVOICE_COMPANY_BANK_ACCOUNTS => 'nullable|notEmpty|json',
        Config::INVOICE_COMPANY_CITY => 'nullable|notEmpty',
        Config::INVOICE_COMPANY_COUNTRY => 'nullable|country',
        Config::INVOICE_COMPANY_NAME => 'nullable|notEmpty',
        Config::INVOICE_COMPANY_POSTAL_CODE => 'nullable|notEmpty',
        Config::INVOICE_COMPANY_VAT_ID => 'nullable|notEmpty',
        Config::INVOICE_CURRENCIES => 'nullable|notEmpty|list:currency',
        Config::INVOICE_ENABLED => 'nullable|boolean',
        Config::INVOICE_NUMBER_FORMAT => 'nullable|notEmpty',
        Config::MAIL_SMTP_ENCRYPTION => 'nullable|notEmpty',
        Config::MAIL_FROM_ADDRESS => 'email',
        Config::MAIL_FROM_NAME => 'nullable|notEmpty',
        Config::MAIL_SMTP_HOST => 'nullable|host',
        Config::MAIL_MAILER => 'nullable|mailer',
        Config::MAIL_SMTP_PASSWORD => 'nullable',
        Config::MAIL_SMTP_PORT => 'nullable|port',
        Config::MAIL_SMTP_USERNAME => 'nullable',
        Config::MAIN_JS_BASE_URL => 'nullable|url',
        Config::MAIN_JS_TLD => 'nullable|host',
        Config::MAX_PAGE_ZONES => 'nullable|positiveInteger',
        Config::NETWORK_DATA_CACHE_TTL => 'nullable|positiveInteger',
        Config::NOW_PAYMENTS_API_KEY => 'nullable',
        Config::NOW_PAYMENTS_CURRENCY => 'nullable|currency',
        Config::NOW_PAYMENTS_EXCHANGE => 'nullable|boolean',
        Config::NOW_PAYMENTS_FEE => 'nullable|commission',
        Config::NOW_PAYMENTS_IPN_SECRET => 'nullable',
        Config::NOW_PAYMENTS_MAX_AMOUNT => 'nullable|positiveInteger',
        Config::NOW_PAYMENTS_MIN_AMOUNT => 'nullable|positiveInteger',
        Config::OPERATOR_RX_FEE => 'nullable|commission',
        Config::OPERATOR_TX_FEE => 'nullable|commission',
        Config::PUBLISHER_APPLY_FORM_URL => 'nullable|url',
        Config::REFERRAL_REFUND_COMMISSION => 'notEmpty|commission',
        Config::REFERRAL_REFUND_ENABLED => 'boolean',
        Config::REGISTRATION_MODE => 'registrationMode',
        Config::SERVE_BASE_URL => 'nullable|url',
        Config::SITE_ACCEPT_BANNERS_MANUALLY => 'boolean',
        Config::SITE_CLASSIFIER_LOCAL_BANNERS => 'siteClassifierLocalBanners',
        Config::SITE_FILTERING_EXCLUDE => 'nullable|json',
        Config::SITE_FILTERING_REQUIRE => 'nullable|json',
        Config::SKYNET_API_KEY => 'nullable|notEmpty',
        Config::SKYNET_API_URL => 'nullable|url',
        Config::SKYNET_CDN_URL => 'nullable|url',
        Config::SUPPORT_CHAT => 'nullable|url',
        Config::SUPPORT_EMAIL => 'email',
        Config::SUPPORT_TELEGRAM => 'nullable|notEmpty',
        Config::TECHNICAL_EMAIL => 'email',
        Config::UPLOAD_LIMIT_IMAGE => 'nullable|positiveInteger',
        Config::UPLOAD_LIMIT_MODEL => 'nullable|positiveInteger',
        Config::UPLOAD_LIMIT_VIDEO => 'nullable|positiveInteger',
        Config::UPLOAD_LIMIT_ZIP => 'nullable|positiveInteger',
        Config::URL => 'url',
        self::REJECTED_DOMAINS => 'nullable|list:domain',
    ];
    private const EMAIL_NOTIFICATION_DELAY_IN_MINUTES = 5;
    private const MAX_VALUE_LENGTH = 65535;
    private const REJECTED_DOMAINS = 'rejected-domains';
    private const RULE_NULLABLE = 'nullable';

    public function fetch(string $key = null): JsonResponse
    {
        if (null !== $key) {
            self::validateKey($key);
            $data = [
                $key => Config::fetchAdminSettings()[$key] ?? null,
            ];
        } else {
            $data = Config::fetchAdminSettings();
        }

        if (null === $key || self::REJECTED_DOMAINS === $key) {
            $data[self::REJECTED_DOMAINS] = SitesRejectedDomain::fetchAll();
        }

        return self::json($data);
    }

    public function fetchPlaceholders(string $key = null): JsonResponse
    {
        if (null !== $key) {
            self::validatePlaceholderKey($key);
            $placeholder = PanelPlaceholder::fetchByType($key);
            return self::json([$key => $placeholder?->content]);
        }

        $data = $this->getPanelPlaceholdersWithNulls(PanelPlaceholder::TYPES_ALLOWED);

        return self::json($data);
    }

    private function getPanelPlaceholdersWithNulls(array $types): array
    {
        $data = [];
        foreach ($types as $type) {
            $data[$type] = null;
        }
        return array_merge(
            $data,
            PanelPlaceholder::fetchByTypes($types)
                ->pluck(PanelPlaceholder::FIELD_CONTENT, PanelPlaceholder::FIELD_TYPE)
                ->toArray()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->input();
        self::validateData($data);
        $result = $this->storeData($data);

        return self::json($result);
    }

    public function storeOne(string $key, Request $request): JsonResponse
    {
        $data = [$key => $request->input('value')];
        self::validateData($data);
        $result = $this->storeData($data);

        return self::json($result);
    }

    public function storePlaceholders(Request $request): JsonResponse
    {
        $data = $request->all();
        self::validatePlaceholdersData($data);
        $result = $this->storePlaceholdersData($data);

        return self::json($result);
    }

    private static function validatePlaceholdersData(array $data): void
    {
        if (!$data) {
            throw new UnprocessableEntityHttpException('Data is required');
        }
        foreach ($data as $type => $content) {
            if (!in_array($type, PanelPlaceholder::TYPES_ALLOWED, true)) {
                throw new UnprocessableEntityHttpException(sprintf('Invalid type (%s)', $type));
            }
            if (null === $content) {
                return;
            }
            if (!is_string($content) || strlen($content) > PanelPlaceholder::MAXIMUM_CONTENT_LENGTH) {
                throw new UnprocessableEntityHttpException(sprintf('Invalid content for type (%s)', $type));
            }
        }
    }

    private function storePlaceholdersData(array $data): array
    {
        $typesToDelete = [];
        $placeholders = [];
        foreach ($data as $type => $content) {
            if (null === $content) {
                $typesToDelete[] = $type;
            } else {
                $placeholders[] = PanelPlaceholder::construct($type, $content);
            }
        }
        $registerDateTime = new DateTimeImmutable();
        $previousEmailSendDateTime = Config::fetchDateTime(Config::PANEL_PLACEHOLDER_NOTIFICATION_TIME);

        DB::beginTransaction();
        try {
            if (!empty($typesToDelete)) {
                PanelPlaceholder::deleteByTypes($typesToDelete);
            }
            if (!empty($placeholders)) {
                PanelPlaceholder::register($placeholders);
            }
            Config::upsertDateTime(Config::PANEL_PLACEHOLDER_UPDATE_TIME, $registerDateTime);

            if ($previousEmailSendDateTime <= $registerDateTime) {
                $emailSendDateTime =
                    $registerDateTime->modify(sprintf('+%d minutes', self::EMAIL_NOTIFICATION_DELAY_IN_MINUTES));
                Config::upsertDateTime(Config::PANEL_PLACEHOLDER_NOTIFICATION_TIME, $emailSendDateTime);
                Mail::to(config('app.technical_email'))
                    ->bcc(config('app.support_email'))
                    ->later($emailSendDateTime, new PanelPlaceholdersChange());
            }
            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();
            Log::error(sprintf('Cannot store placeholders: (%s)', $exception->getMessage()));
            throw new RuntimeException();
        }

        $types = array_keys($data);

        return $this->getPanelPlaceholdersWithNulls($types);
    }

    private function storeData(array $data): array
    {
        $appendRejectedDomains = false;
        try {
            if (array_key_exists(self::REJECTED_DOMAINS, $data)) {
                SitesRejectedDomain::storeDomains(array_filter(explode(',', $data[self::REJECTED_DOMAINS] ?? '')));
                unset($data[self::REJECTED_DOMAINS]);
                $appendRejectedDomains = true;
            }
            Config::updateAdminSettings($data);
        } catch (Throwable $exception) {
            Log::error(sprintf('Cannot store configuration: (%s)', $exception->getMessage()));
            throw new RuntimeException('Cannot store configuration');
        }

        $settings = array_intersect_key(Config::fetchAdminSettings(), $data);
        if ($appendRejectedDomains) {
            $settings[self::REJECTED_DOMAINS] = SitesRejectedDomain::fetchAll();
        }

        return $settings;
    }

    private static function validateData(array $data): void
    {
        if (!$data) {
            throw new UnprocessableEntityHttpException('Data is required');
        }

        foreach ($data as $field => $value) {
            self::validateKeyAndValue($field, $value);
        }
    }

    private static function validateAccountId(string $field, string $value): void
    {
        if (!AccountId::isValid($value)) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be an account ID', $field));
        }
    }

    private static function validateAppCurrency(string $field, string $value): void
    {
        if (null === Currency::tryFrom($value)) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be a currency', $field));
        }
        if ((new UserLedgerEntry())->count() > 0) {
            throw new UnprocessableEntityHttpException('App currency cannot be changed');
        }
    }

    private static function validatePositiveInteger(string $field, string $value): void
    {
        if (false === filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]])) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be a positive integer', $field));
        }
    }

    private static function validateBoolean(string $field, string $value): void
    {
        if (!in_array($value, ['0', '1'])) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be a boolean', $field));
        }
    }

    private static function validateEmail(string $field, string $value): void
    {
        if (false === filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be an e-mail', $field));
        }
    }

    private static function validateHex(string $field, string $value, ?string $length = null): void
    {
        if (null !== $length && strlen($value) !== (int)$length) {
            throw new UnprocessableEntityHttpException(
                sprintf('Field `%s` must be have %d characters', $field, (int)$length)
            );
        }
        if (1 !== preg_match('/^[\dA-Z]{64}$/', $value)) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be a hexadecimal string', $field));
        }
    }

    private static function validateKeyAndValue($field, $value): void
    {
        self::validateKey($field);

        $rules = explode('|', self::ALLOWED_KEYS[$field]);

        if (in_array(self::RULE_NULLABLE, $rules) && null === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be a string', $field));
        }
        if (strlen($value) > self::MAX_VALUE_LENGTH) {
            throw new UnprocessableEntityHttpException(
                sprintf('Field `%s` must have at most %d characters', $field, self::MAX_VALUE_LENGTH)
            );
        }

        foreach ($rules as $rule) {
            if (self::RULE_NULLABLE === $rule) {
                continue;
            }
            $ruleParts = explode(':', $rule);
            $signature = Str::camel('validate_' . $ruleParts[0]);
            $parameters = explode(',', $ruleParts[1] ?? '');
            self::{$signature}($field, $value, ...$parameters);
        }
    }

    private static function validateNotEmpty(string $field, string $value): void
    {
        if ('' === $value) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` cannot be empty', $field));
        }
    }

    private static function validateClickAmount(string $field, string $value): void
    {
        if (
            false === filter_var(
                $value,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0, 'max_range' => AdsConverter::TOTAL_SUPPLY]]
            )
        ) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be an amount in clicks', $field));
        }
    }

    private static function validateCommission(string $field, string $value): void
    {
        if (
            false === filter_var(
                $value,
                FILTER_VALIDATE_FLOAT,
                ['options' => ['min_range' => 0, 'max_range' => 1]]
            )
        ) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be a fraction <0; 1>', $field));
        }
    }

    private static function validateCountry(string $field, string $value): void
    {
        if (1 !== preg_match('/^[A-Z]{2}$/', $value)) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be in valid format', $field));
        }
    }

    private static function validateCurrency(string $field, string $value): void
    {
        if (1 !== preg_match('/^[A-Z]{3,}$/', $value)) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be a currency', $field));
        }
    }

    private static function validateDomain(string $field, string $value): void
    {
        if (!SiteValidator::isDomainValid($value)) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be a domain', $field));
        }
    }

    private static function validateHost(string $field, string $value): void
    {
        if (false === filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be a host', $field));
        }
    }

    private static function validateJson(string $field, string $value): void
    {
        if (null === json_decode($value, true)) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be a JSON', $field));
        }
    }

    private static function validateKey(string $key): void
    {
        if (!isset(self::ALLOWED_KEYS[$key])) {
            throw new UnprocessableEntityHttpException(sprintf('Key `%s` is not supported', $key));
        }
    }

    private static function validatePlaceholderKey(string $key): void
    {
        if (!in_array($key, PanelPlaceholder::TYPES_ALLOWED, true)) {
            throw new UnprocessableEntityHttpException(sprintf('Key `%s` is not supported', $key));
        }
    }

    private static function validateLicenseKey(string $field, string $value): void
    {
        if (1 !== preg_match('/^(COM|SRV)-[\da-z]{6}-[\da-z]{5}-[\da-z]{5}-[\da-z]{4}-[\da-z]{4}$/i', $value)) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be a license key', $field));
        }
    }

    private static function validateList(string $field, string $value, string $type): void
    {
        if ('' === $value) {
            return;
        }

        foreach (explode(',', $value) as $item) {
            $signature = Str::camel('validate_' . $type);
            try {
                self::{$signature}($field, $item);
            } catch (UnprocessableEntityHttpException) {
                throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be a list of %s', $field, $type));
            }
        }
    }

    private static function validateMailer(string $field, string $value): void
    {
        $allowedMailers = array_keys(config('mail.mailers'));
        if (!in_array($value, $allowedMailers, true)) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be a known mailer', $field));
        }
    }

    private static function validatePort(string $field, string $value): void
    {
        if (
            false === filter_var(
                $value,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 0, 'max_range' => 65535]]
            )
        ) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be a port number', $field));
        }
    }

    private static function validateRegistrationMode(string $field, string $value): void
    {
        if (!in_array($value, RegistrationMode::cases(), true)) {
            throw new UnprocessableEntityHttpException(
                sprintf(
                    'Field `%s` must be one of %s',
                    $field,
                    implode(', ', RegistrationMode::cases())
                )
            );
        }
    }

    private static function validateSiteClassifierLocalBanners(string $field, string $value): void
    {
        if (!in_array($value, Config::ALLOWED_CLASSIFIER_LOCAL_BANNERS_OPTIONS, true)) {
            throw new UnprocessableEntityHttpException(
                sprintf(
                    'Field `%s` must be one of %s',
                    $field,
                    implode(', ', Config::ALLOWED_CLASSIFIER_LOCAL_BANNERS_OPTIONS)
                )
            );
        }
    }

    private static function validateUrl(string $field, string $value): void
    {
        if (false === filter_var($value, FILTER_VALIDATE_URL)) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be a url', $field));
        }
    }

    private static function validateUserRole(string $field, string $value): void
    {
        if (!in_array($value, UserRole::cases())) {
            throw new UnprocessableEntityHttpException(
                sprintf(
                    'Field `%s` must be one of %s',
                    $field,
                    implode(', ', UserRole::cases())
                )
            );
        }
    }
}
