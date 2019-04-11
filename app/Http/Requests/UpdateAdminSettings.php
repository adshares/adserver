<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Http\Requests;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Rules\AccountIdRule;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\Commission;
use Adshares\Common\Domain\ValueObject\Email;

class UpdateAdminSettings extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $input = $this->all();
        $settings = $input['settings'];

        //TODO: remove when front done
        $settings['cold_wallet_is_active'] = $settings['cold_wallet_is_active'] ?? $settings['hotwallet_is_active'];
        $settings['cold_wallet_min_value'] = $settings['cold_wallet_min_value'] ?? $settings['hotwallet_min_value'];
        $settings['cold_wallet_max_value'] = $settings['cold_wallet_max_value'] ?? $settings['hotwallet_max_value'];
        $settings['cold_wallet_address'] = $settings['cold_wallet_address'] ?? $settings['hotwallet_address'];
        unset($settings['hotwallet_min_value'], $settings['hotwallet_max_value'], $settings['hotwallet_address']);
        //TODO: ^^^

        $isHotWalletActive = (bool)$settings['cold_wallet_is_active'];

        if ($isHotWalletActive === false) {
            unset($settings['cold_wallet_min_value'], $settings['cold_wallet_max_value'], $settings['cold_wallet_address']);
        }

        $this->replace(['settings' => $settings]);
    }

    public function rules(): array
    {
        $blacklistedAccountIds = [new AccountId(config('app.adshares_address'))];

        return [
            'settings.cold_wallet_is_active' => 'required|boolean',
            'settings.cold_wallet_min_value' => [
                'required_if:settings.cold_wallet_is_active,1',
                'integer',
                'min:0',
                'max:100000000000000000',
            ],
            'settings.cold_wallet_max_value' => [
                'required_if:settings.cold_wallet_is_active,1',
                'integer',
                'min:1',
                'max:100000000000000000',
                'gt:settings.cold_wallet_min_value',
            ],
            'settings.cold_wallet_address' => [
                'required_if:settings.cold_wallet_is_active,1',
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
            Config::COLD_WALLET_IS_ACTIVE => (int)$values['cold_wallet_is_active'],
            Config::ADSERVER_NAME => (string)$values['adserver_name'],
            Config::TECHNICAL_EMAIL => (new Email($values['technical_email']))->toString(),
            Config::SUPPORT_EMAIL => (new Email($values['support_email']))->toString(),
        ];

        if (isset($values['cold_wallet_min_value'])) {
            $data[Config::HOT_WALLET_MIN_VALUE] = (int)$values['cold_wallet_min_value'];
        }

        if (isset($values['cold_wallet_max_value'])) {
            $data[Config::HOT_WALLET_MAX_VALUE] = (int)$values['cold_wallet_max_value'];
        }

        if (isset($values['cold_wallet_address'])) {
            $data[Config::COLD_WALLET_ADDRESS] = (new AccountId((string)$values['cold_wallet_address']))->toString();
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
