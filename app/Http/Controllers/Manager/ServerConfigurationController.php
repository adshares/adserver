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
use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Models\Config;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Config\RegistrationMode;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

class ServerConfigurationController extends Controller
{
    private const ALLOWED_KEYS = [
        Config::ADSHARES_ADDRESS => 'accountId',
        Config::ADSHARES_NODE_HOST => 'host',
        Config::ADSHARES_NODE_PORT => 'nullable|port',
        Config::ADSHARES_SECRET => 'hex:64',
        Config::ADSERVER_NAME => 'notEmpty',
        Config::AUTO_CONFIRMATION_ENABLED => 'boolean',
        Config::AUTO_REGISTRATION_ENABLED => 'boolean',
        Config::CAMPAIGN_MIN_BUDGET => 'nullable|clickAmount',
        Config::CAMPAIGN_MIN_CPA => 'nullable|clickAmount',
        Config::CAMPAIGN_MIN_CPM => 'nullable|clickAmount',
        Config::COLD_WALLET_ADDRESS => 'accountId',
        Config::COLD_WALLET_IS_ACTIVE => 'boolean',
        Config::EMAIL_VERIFICATION_REQUIRED => 'boolean',
        Config::HOT_WALLET_MIN_VALUE => 'nullable|clickAmount',
        Config::HOT_WALLET_MAX_VALUE => 'nullable|clickAmount',
        Config::INVOICE_COMPANY_ADDRESS => 'notEmpty',
        Config::INVOICE_COMPANY_BANK_ACCOUNTS => 'notEmpty|json',
        Config::INVOICE_COMPANY_CITY => 'notEmpty',
        Config::INVOICE_COMPANY_COUNTRY => 'country',
        Config::INVOICE_COMPANY_NAME => 'notEmpty',
        Config::INVOICE_COMPANY_POSTAL_CODE => 'notEmpty',
        Config::INVOICE_COMPANY_VAT_ID => 'notEmpty',
        Config::INVOICE_CURRENCIES => 'currenciesList',
        Config::INVOICE_ENABLED => 'boolean',
        Config::INVOICE_NUMBER_FORMAT => 'notEmpty',
        Config::OPERATOR_RX_FEE => 'nullable|commission',
        Config::OPERATOR_TX_FEE => 'nullable|commission',
        Config::REFERRAL_REFUND_COMMISSION => 'notEmpty|commission',
        Config::REFERRAL_REFUND_ENABLED => 'boolean',
        Config::REGISTRATION_MODE => 'registrationMode',
        Config::SITE_ACCEPT_BANNERS_MANUALLY => 'boolean',
        Config::SITE_CLASSIFIER_LOCAL_BANNERS => 'siteClassifierLocalBanners',
        Config::SUPPORT_EMAIL => 'email',
        Config::TECHNICAL_EMAIL => 'email',
    ];
    private const MAX_VALUE_LENGTH = 255;
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

        return self::json($data);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->input();
        $this->validateData($data);
        $this->storeData($data);

        return self::json();
    }

    public function storeOne(string $key, Request $request): JsonResponse
    {
        $data = [$key => $request->input('value')];
        $this->validateData($data);
        $this->storeData($data);

        return self::json();
    }

    private function storeData(array $data): void
    {
        try {
            Config::updateAdminSettings($data);
        } catch (Throwable $exception) {
            throw new RuntimeException('Cannot store configuration');
        }
    }

    private function validateData(array $data): void
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

    private static function validateBoolean(string $field, string $value): void
    {
        if (!in_array($value, ['0', '1'])) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be a boolean', $field));
        }
    }

    private static function validateDateTime(string $field, string $value): void
    {
        if (false === DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value)) {
            throw new UnprocessableEntityHttpException(
                sprintf('Field `%s` must be a date in ISO-8601 format', $field)
            );
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
        if (1 !== preg_match('/^[0-9A-Z]$/', $value)) {
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
        if (empty($value)) {
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

    private static function validateCurrenciesList(string $field, string $value): void
    {
        if (1 !== preg_match('/^[A-Z]{2,}((,[A-Z]{2,})+)?$/', $value)) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be in valid format', $field));
        }
    }

    private static function validateHost(string $field, string $value): void
    {
        if (false === filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be a host', $field));
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

    private static function validateKey(string $key): void
    {
        if (!isset(self::ALLOWED_KEYS[$key])) {
            throw new UnprocessableEntityHttpException(sprintf('Key `%s` is not supported', $key));
        }
    }

    private static function validateJson(string $field, string $value): void
    {
        if (null === json_decode($value, true)) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be a JSON', $field));
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
}
