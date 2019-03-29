<?php

namespace Adshares\Adserver\Http\Requests;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Rules\AccountIdRule;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\Commission;
use Adshares\Common\Domain\ValueObject\Email;

class UpdateAdminSettings extends FormRequest
{
    private const FIELD_HOT_WALLET_IS_ACTIVE = 'hotwallet_is_active';

    private const FIELD_HOT_WALLET_MAX_VALUE = 'hotwallet_max_value';

    private const FIELD_HOT_WALLET_MIN_VALUE = 'hotwallet_min_value';

    private const FIELD_HOT_WALLET_ADDRESS = 'hotwallet_address';

    private const FIELD_ADSERVER_NAME = 'adserver_name';

    private const FIELD_TECHNICAL_EMAIL = 'technical_email';

    private const FIELD_SUPPORT_EMAIL = 'support_email';

    private const FIELD_ADVERTISER_COMMISSION = 'advertiser_commission';

    private const FIELD_PUBLISHER_COMMISSION = 'publisher_commission';

    private const SETTINGS = 'settings';

    private const PREFIX_SETTINGS = self::SETTINGS.'.';

    private const RULE_REQUIRED_IF_HOT_WALLET_IS_ACTIVE = 'required_if:'
    .self::PREFIX_SETTINGS
    .self::FIELD_HOT_WALLET_IS_ACTIVE
    .',1';

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $input = $this->all();
        $settings = $input[self::SETTINGS];
        $isHotWalletActive = (bool)$settings[self::FIELD_HOT_WALLET_IS_ACTIVE];

        if ($isHotWalletActive === false) {
            unset(
                $settings[self::FIELD_HOT_WALLET_MIN_VALUE],
                $settings[self::FIELD_HOT_WALLET_MAX_VALUE],
                $settings[self::FIELD_HOT_WALLET_ADDRESS]
            );
        }

        $this->replace([self::SETTINGS => $settings]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $blacklistedAccountIds = [new AccountId(config('app.adshares_address'))];

        return [
            self::PREFIX_SETTINGS.self::FIELD_HOT_WALLET_IS_ACTIVE => 'required|boolean',
            self::PREFIX_SETTINGS.self::FIELD_HOT_WALLET_MIN_VALUE => [
                self::RULE_REQUIRED_IF_HOT_WALLET_IS_ACTIVE,
                'integer',
                'min:0',
                'max:100000000000000000',
            ],
            self::PREFIX_SETTINGS.self::FIELD_HOT_WALLET_MAX_VALUE => [
                self::RULE_REQUIRED_IF_HOT_WALLET_IS_ACTIVE,
                'integer',
                'min:1',
                'max:100000000000000000',
                'gt:settings.hotwallet_min_value',
            ],
            self::PREFIX_SETTINGS.self::FIELD_HOT_WALLET_ADDRESS => [
                self::RULE_REQUIRED_IF_HOT_WALLET_IS_ACTIVE,
                new AccountIdRule($blacklistedAccountIds),
            ],
            self::PREFIX_SETTINGS.self::FIELD_ADSERVER_NAME => 'required|string|max:255',
            self::PREFIX_SETTINGS.self::FIELD_TECHNICAL_EMAIL => 'required|email|max:255',
            self::PREFIX_SETTINGS.self::FIELD_SUPPORT_EMAIL => 'required|email|max:255',
            self::PREFIX_SETTINGS.self::FIELD_ADVERTISER_COMMISSION => 'numeric|between:0,1|nullable',
            self::PREFIX_SETTINGS.self::FIELD_PUBLISHER_COMMISSION => 'numeric|between:0,1|nullable',
        ];
    }

    public function toConfigFormat(): array
    {
        $values = $this->validated()[self::SETTINGS];

        $data = [
            Config::HOT_WALLET_IS_ACTIVE => (int)$values[self::FIELD_HOT_WALLET_IS_ACTIVE],
            Config::ADSERVER_NAME => (string)$values[self::FIELD_ADSERVER_NAME],
            Config::TECHNICAL_EMAIL => (new Email($values[self::FIELD_TECHNICAL_EMAIL]))->toString(),
            Config::SUPPORT_EMAIL => (new Email($values[self::FIELD_SUPPORT_EMAIL]))->toString(),
        ];

        if (isset($values[self::FIELD_HOT_WALLET_MIN_VALUE])) {
            $data[Config::HOT_WALLET_MIN_VALUE] = (int)$values[self::FIELD_HOT_WALLET_MIN_VALUE];
        }

        if (isset($values[self::FIELD_HOT_WALLET_MAX_VALUE])) {
            $data[Config::HOT_WALLET_MAX_VALUE] = (int)$values[self::FIELD_HOT_WALLET_MAX_VALUE];
        }

        if (isset($values[self::FIELD_HOT_WALLET_ADDRESS])) {
            $data[Config::HOT_WALLET_ADDRESS] =
                (new AccountId((string)$values[self::FIELD_HOT_WALLET_ADDRESS]))->toString();
        }

        if (isset($values[self::FIELD_ADVERTISER_COMMISSION])) {
            $data[Config::OPERATOR_TX_FEE] =
                (new Commission((float)$values[self::FIELD_ADVERTISER_COMMISSION]))->getValue();
        }

        if (isset($values[self::FIELD_PUBLISHER_COMMISSION])) {
            $data[Config::OPERATOR_RX_FEE] =
                (new Commission((float)$values[self::FIELD_PUBLISHER_COMMISSION]))->getValue();
        }

        return $data;
    }
}
