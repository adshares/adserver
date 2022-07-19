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
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

class ServerConfigurationController extends Controller
{
    private const ALLOWED_KEYS = [
        Config::ADSERVER_NAME,
        Config::AUTO_CONFIRMATION_ENABLED,
        Config::AUTO_REGISTRATION_ENABLED,
        Config::COLD_WALLET_ADDRESS,
        Config::COLD_WALLET_IS_ACTIVE,
        Config::EMAIL_VERIFICATION_REQUIRED,
        Config::HOT_WALLET_MIN_VALUE,
        Config::HOT_WALLET_MAX_VALUE,
        Config::INVOICE_ENABLED,
        Config::PANEL_PLACEHOLDER_NOTIFICATION_TIME,
        Config::PANEL_PLACEHOLDER_UPDATE_TIME,
        Config::REFERRAL_REFUND_ENABLED,
        Config::REGISTRATION_MODE,
        Config::SITE_ACCEPT_BANNERS_MANUALLY,
        Config::SITE_CLASSIFIER_LOCAL_BANNERS,
        Config::SUPPORT_EMAIL,
        Config::TECHNICAL_EMAIL,
//        Config::OPERATOR_TX_FEE,
//        Config::OPERATOR_RX_FEE,
//        Config::LICENCE_TX_FEE,
//        Config::LICENCE_RX_FEE,
//        Config::LICENCE_ACCOUNT,
//        Config::SITE_VERIFICATION_NOTIFICATION_TIME_THRESHOLD,
//        Config::REFERRAL_REFUND_COMMISSION,
//        Config::INVOICE_CURRENCIES,
//        Config::INVOICE_NUMBER_FORMAT,
//        Config::INVOICE_COMPANY_NAME,
//        Config::INVOICE_COMPANY_ADDRESS,
//        Config::INVOICE_COMPANY_POSTAL_CODE,
//        Config::INVOICE_COMPANY_CITY,
//        Config::INVOICE_COMPANY_COUNTRY,
//        Config::INVOICE_COMPANY_VAT_ID,
//        Config::INVOICE_COMPANY_BANK_ACCOUNTS,

// not administrator's configuration
//        Config::ADS_LOG_START,
//        Config::ADSELECT_INVENTORY_EXPORT_TIME,
//        Config::ADPAY_BID_STRATEGY_EXPORT_TIME,
//        Config::ADPAY_CAMPAIGN_EXPORT_TIME,
//        Config::ADPAY_LAST_EXPORTED_CONVERSION_TIME,
//        Config::ADPAY_LAST_EXPORTED_EVENT_TIME,
//        Config::LAST_UPDATED_IMPRESSION_ID,
//        Config::OPERATOR_WALLET_EMAIL_LAST_TIME,
    ];
    private const MAX_VALUE_LENGTH = 255;

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
        $this->storeData($this->validatedData($data));

        return self::json();
    }

    public function storeOne(string $key, Request $request): JsonResponse
    {
        $data = [$key => $request->input('value')];
        $this->storeData($this->validatedData($data));

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

    private function validatedData(array $data): array
    {
        if (!$data) {
            throw new UnprocessableEntityHttpException('Data is required');
        }

        $validatedData = [];
        foreach ($data as $field => $value) {
            self::validateKeyAndValue($field, $value);

            if (self::isEmailField($field) && false === ($value = filter_var($value, FILTER_VALIDATE_EMAIL))) {
                throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be an e-mail', $field));
            } elseif (self::isBooleanField($field)) {
                if (null === ($value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE))) {
                    throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be a boolean', $field));
                }
                $value = $value ? '1' : '0';
            } elseif (self::isClickAmountField($field)) {
                self::validateClickAmount($field, $value);
            } elseif (self::isAccountIdField($field)) {
                self::validateAccountIdField($field, $value);
            } elseif (self::isDateTimeField($field)) {
                self::validateDateTime($field, $value);
            } elseif (Config::REGISTRATION_MODE === $field) {
                self::validateRegistrationMode($field, $value);
            } elseif (Config::SITE_CLASSIFIER_LOCAL_BANNERS === $field) {
                self::validateSiteClassifierLocalBanners($field, $value);
            }

            $validatedData[$field] = $value;
        }

        return $validatedData;
    }

    private static function validateAccountIdField(string $field, string $value): void
    {
        if (!AccountId::isValid($value)) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be an account ID', $field));
        }
    }

    private static function validateDateTime(string $field, $value): void
    {
        if (false === DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value)) {
            throw new UnprocessableEntityHttpException(
                sprintf('Field `%s` must be a date in ISO-8601 format', $field)
            );
        }
    }

    private static function validateKeyAndValue($field, $value): void
    {
        if (!in_array($field, self::ALLOWED_KEYS, true)) {
            throw new UnprocessableEntityHttpException(sprintf('Key `%s` is not supported', $field));
        }
        if (!is_string($value)) {
            throw new UnprocessableEntityHttpException(sprintf('Field `%s` must be a string', $field));
        }
        if (strlen($value) > self::MAX_VALUE_LENGTH) {
            throw new UnprocessableEntityHttpException(
                sprintf('Field `%s` must have at most %d characters', $field, self::MAX_VALUE_LENGTH)
            );
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

    private static function isAccountIdField(string $field): bool
    {
        return in_array($field, [Config::COLD_WALLET_ADDRESS], true);
    }

    private static function isBooleanField(string $field): bool
    {
        return in_array(
            $field,
            [
                Config::AUTO_CONFIRMATION_ENABLED,
                Config::AUTO_REGISTRATION_ENABLED,
                Config::EMAIL_VERIFICATION_REQUIRED,
                Config::COLD_WALLET_IS_ACTIVE,
                Config::INVOICE_ENABLED,
                Config::REFERRAL_REFUND_ENABLED,
                Config::SITE_ACCEPT_BANNERS_MANUALLY,
            ],
            true
        );
    }

    private static function isClickAmountField(string $field): bool
    {
        return in_array($field, [Config::HOT_WALLET_MIN_VALUE, Config::HOT_WALLET_MAX_VALUE], true);
    }

    private static function isDateTimeField(string $field): bool
    {
        return in_array(
            $field,
            [Config::PANEL_PLACEHOLDER_NOTIFICATION_TIME, Config::PANEL_PLACEHOLDER_UPDATE_TIME],
            true
        );
    }

    private static function isEmailField(string $field): bool
    {
        return in_array($field, [Config::TECHNICAL_EMAIL, Config::SUPPORT_EMAIL], true);
    }
}
