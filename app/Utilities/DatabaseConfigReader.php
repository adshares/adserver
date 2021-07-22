<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Utilities;

use Adshares\Adserver\Models\Config;
use Illuminate\Support\Facades\Config as SystemConfig;

class DatabaseConfigReader
{
    public static function overwriteAdministrationConfig(): void
    {
        $settings = Config::fetchAdminSettings();

        $hotWalletMinValue = $settings[Config::HOT_WALLET_MIN_VALUE];
        $hotWalletMaxValue = $settings[Config::HOT_WALLET_MAX_VALUE];
        $coldWalletAddress = $settings[Config::COLD_WALLET_ADDRESS];
        $serverName = $settings[Config::ADSERVER_NAME];
        $technicalEmail = $settings[Config::TECHNICAL_EMAIL];
        $supportEmail = $settings[Config::SUPPORT_EMAIL];
        $operatorTxFee = $settings[Config::OPERATOR_TX_FEE];
        $operatorRxFee = $settings[Config::OPERATOR_RX_FEE];

        SystemConfig::set('app.adshares_wallet_min_amount', $hotWalletMinValue);
        SystemConfig::set('app.adshares_wallet_max_amount', $hotWalletMaxValue);
        SystemConfig::set('app.adshares_wallet_cold_address', $coldWalletAddress);
        SystemConfig::set('app.name', $serverName);
        SystemConfig::set('app.adshares_operator_email', $technicalEmail);
        SystemConfig::set('app.adshares_support_email', $supportEmail);
        SystemConfig::set('app.' . Config::OPERATOR_TX_FEE, $operatorTxFee);
        SystemConfig::set('app.' . Config::OPERATOR_RX_FEE, $operatorRxFee);
    }
}
