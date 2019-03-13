<?php

namespace Adshares\Adserver\Http\Requests;

use Adshares\Adserver\Models\Config;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminSettings extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'settings.hotwallet_min_value' => 'required|integer|min:0',
            'settings.hotwallet_max_value' => 'required|integer|min:1|gt:settings.hotwallet_min_value',
            'settings.adserver_name' => 'required|string|max:255',
            'settings.technical_email' => 'required|email|max:255',
            'settings.support_email' => 'required|email|max:255',
            'settings.advertiser_commission' => 'numeric|max:100|nullable',
            'settings.publisher_commission' => 'numeric|max:100|nullable',
        ];
    }

    public function toConfigFormat(): array
    {
        $values = $this->validated()['settings'];

        $data = [
            Config::HOT_WALLET_MIN_VALUE => $values['hotwallet_min_value'],
            Config::HOT_WALLET_MAX_VALUE => $values['hotwallet_max_value'],
            Config::ADSERVER_NAME => $values['adserver_name'],
            Config::TECHNICAL_EMAIL => $values['technical_email'],
            Config::SUPPORT_EMAIL => $values['support_email'],
        ];

        if (isset($values['advertiser_commission'])) {
            $data[Config::OPERATOR_TX_FEE] = $values['advertiser_commission'];
        }

        if (isset($values['publisher_commission'])) {
            $data[Config::OPERATOR_RX_FEE] = $values['publisher_commission'];
        }

        return $data;
    }
}
