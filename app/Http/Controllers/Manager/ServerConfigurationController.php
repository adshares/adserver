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
        Config::ADSERVER_NAME => 'required',
        Config::AUTO_CONFIRMATION_ENABLED => 'required|boolean',
        Config::AUTO_REGISTRATION_ENABLED => 'required|boolean',
        Config::COLD_WALLET_ADDRESS => 'required|accountId',
        Config::COLD_WALLET_IS_ACTIVE => 'required|boolean',
        Config::EMAIL_VERIFICATION_REQUIRED => 'required|boolean',
        Config::HOT_WALLET_MIN_VALUE => 'required|clickAmount',
        Config::HOT_WALLET_MAX_VALUE => 'required|clickAmount',
        Config::INVOICE_COMPANY_ADDRESS => 'required',
        Config::INVOICE_COMPANY_BANK_ACCOUNTS => 'required|json',
        Config::INVOICE_COMPANY_CITY => 'required',
        Config::INVOICE_COMPANY_COUNTRY => 'required|country',
        Config::INVOICE_COMPANY_NAME => 'required',
        Config::INVOICE_COMPANY_POSTAL_CODE => 'required',
        Config::INVOICE_COMPANY_VAT_ID => 'required',
        Config::INVOICE_CURRENCIES => 'required|currenciesList',
        Config::INVOICE_ENABLED => 'required|boolean',
        Config::INVOICE_NUMBER_FORMAT => 'required',
        Config::OPERATOR_RX_FEE => 'required|commission',
        Config::OPERATOR_TX_FEE => 'required|commission',
        Config::PANEL_PLACEHOLDER_NOTIFICATION_TIME => 'required|dateTime',
        Config::PANEL_PLACEHOLDER_UPDATE_TIME => 'required|dateTime',
        Config::REFERRAL_REFUND_COMMISSION => 'required|commission',
        Config::REFERRAL_REFUND_ENABLED => 'required|boolean',
        Config::REGISTRATION_MODE => 'required|registrationMode',
        Config::SITE_ACCEPT_BANNERS_MANUALLY => 'required|boolean',
        Config::SITE_CLASSIFIER_LOCAL_BANNERS => 'required|siteClassifierLocalBanners',
        Config::SUPPORT_EMAIL => 'required|email',
        Config::TECHNICAL_EMAIL => 'required|email',

// not administrator's configuration
//        Config::ADS_LOG_START,
//        Config::ADSELECT_INVENTORY_EXPORT_TIME,
//        Config::ADPAY_BID_STRATEGY_EXPORT_TIME,
//        Config::ADPAY_CAMPAIGN_EXPORT_TIME,
//        Config::ADPAY_LAST_EXPORTED_CONVERSION_TIME,
//        Config::ADPAY_LAST_EXPORTED_EVENT_TIME,
//        Config::LAST_UPDATED_IMPRESSION_ID,
//        Config::LICENCE_ACCOUNT,
//        Config::LICENCE_RX_FEE,
//        Config::LICENCE_TX_FEE,
//        Config::OPERATOR_WALLET_EMAIL_LAST_TIME,
//        Config::SITE_VERIFICATION_NOTIFICATION_TIME_THRESHOLD,
    ];
    private const MAX_VALUE_LENGTH = 255;
    private const RULE_NULLABLE = 'nullable';

    public function fetch(string $key = null): JsonResponse
    {
        if (null !== $key) {
            $collection = Config::where('key', $key);
        } else {
            $collection = Config::all();
        }
        $data = $collection
            ->pluck('value', 'key')
            ->toArray();

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
        DB::beginTransaction();
        try {
            foreach ($data as $key => $value) {
                Config::upsertByKey($key, $value);
            }
            DB::commit();
        } catch (Throwable $exception) {
            Log::error(sprintf('Exception during server configuration update (%s)', $exception->getMessage()));
            DB::rollBack();
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

    private static function validateKeyAndValue($field, $value): void
    {
        if (!isset(self::ALLOWED_KEYS[$field])) {
            throw new UnprocessableEntityHttpException(sprintf('Key `%s` is not supported', $field));
        }

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
            $name = Str::camel('validate_' . $rule);
            self::{$name}($field, $value);
        }
    }

    private static function validateRequired(string $field, string $value): void
    {
        if (empty($value)) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` is required', $field));
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

    private static function validateSiteClassifierLocalBanners($field, $value): void
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
