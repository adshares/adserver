<?php

namespace Adshares\Adserver\Http\Requests;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Rules\AccountIdRule;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\Commission;
use Adshares\Common\Domain\ValueObject\Email;

class UpdateAdminSettings extends FormRequest
{
    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        $input = $this->all();
        $settings = $input['settings'];
        $isHotWalletActive = (bool)$settings['hotwallet_is_active'];

        if ($isHotWalletActive === false) {
            unset($settings['hotwallet_min_value'], $settings['hotwallet_max_value'], $settings['hotwallet_address']);
        }

        $this->replace(['settings' => $settings]);
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
            'settings.hotwallet_is_active' => 'required|boolean',
            'settings.hotwallet_min_value' => 'required_if:settings.hotwallet_is_active,1|integer|min:0|max:100000000000000000',
            'settings.hotwallet_max_value' => [
                'required_if:settings.hotwallet_is_active,1',
                'integer',
                'min:1',
                'max:100000000000000000',
                'gt:settings.hotwallet_min_value',
            ],
            'settings.hotwallet_address' => [
                'required_if:settings.hotwallet_is_active,1',
                new AccountIdRule($blacklistedAccountIds),
            ],
            'settings.adserver_name' => 'required|string|max:255',
            'settings.technical_email' => 'required|email|max:255',
            'settings.support_email' => 'required|email|max:255',
            'settings.advertiser_commission' => 'numeric|between:0,1|nullable',
            'settings.publisher_commission' => 'numeric|between:0,1|nullable',
        ];
    }

    public function toConfigFormat(): array
    {
        $values = $this->validated()['settings'];

        $data = [
            Config::HOT_WALLET_IS_ACTIVE => (int)$values['hotwallet_is_active'],
            Config::ADSERVER_NAME => (string)$values['adserver_name'],
            Config::TECHNICAL_EMAIL => (new Email($values['technical_email']))->toString(),
            Config::SUPPORT_EMAIL => (new Email($values['support_email']))->toString(),
        ];

        if (isset($values['hotwallet_min_value'])) {
            $data[Config::HOT_WALLET_MIN_VALUE] = (int)$values['hotwallet_min_value'];
        }

        if (isset($values['hotwallet_max_value'])) {
            $data[Config::HOT_WALLET_MAX_VALUE] = (int)$values['hotwallet_max_value'];
        }

        if (isset($values['hotwallet_address'])) {
            $data[Config::HOT_WALLET_ADDRESS] = (new AccountId((string)$values['hotwallet_address']))->toString();
        }

        if (isset($values['advertiser_commission'])) {
            $data[Config::OPERATOR_TX_FEE] = (new Commission((float)$values['advertiser_commission']))->getValue();
        }

        if (isset($values['publisher_commission'])) {
            $data[Config::OPERATOR_RX_FEE] = (new Commission((float)$values['publisher_commission']))->getValue();
        }

        return $data;
    }
}
