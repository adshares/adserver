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

namespace Adshares\Adserver\Utilities;

use Config as SystemConfig;
use Adshares\Adserver\Models\Config;

class DatabaseConfigReader
{
    public static function overwriteAdministrationConfig(): void
    {
        $settings = Config::fetchAdminSettings();
        $hotWalletMinValue = $settings[Config::HOT_WALLET_MIN_VALUE] ?? null;
        $hotWalletMaxValue = $settings[Config::HOT_WALLET_MAX_VALUE] ?? null;
        $serverName = $settings[Config::ADSERVER_NAME] ?? null;
        $technicalEmail = $settings[Config::TECHNICAL_EMAIL] ?? null;
        $supportEmail = $settings[Config::SUPPORT_EMAIL] ?? null;
        $operatorTxFee = $settings[Config::OPERATOR_TX_FEE] ?? null;
        $operatorRxFee = $settings[Config::OPERATOR_RX_FEE] ?? null;

        if ($hotWalletMinValue) {
            SystemConfig::set('app.adshares_wallet_min_amount', $hotWalletMinValue);
        }

        if ($hotWalletMaxValue) {
            SystemConfig::set('app.adshares_wallet_max_amount', $hotWalletMaxValue);
        }

        if ($serverName) {
            SystemConfig::set('app.name', $serverName);
        }

        if ($technicalEmail) {
            SystemConfig::set('app.adshares_operator_email', $technicalEmail);
        }

        if ($supportEmail) {
            SystemConfig::set('app.adshares_support_email', $supportEmail);
        }

        if ($operatorTxFee) {
            SystemConfig::set('app.'.Config::OPERATOR_TX_FEE, $operatorTxFee);
        }

        if ($operatorRxFee) {
            SystemConfig::set('app.'.Config::OPERATOR_RX_FEE, $operatorRxFee);
        }
    }
}
