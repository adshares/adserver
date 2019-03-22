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

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\Config;
use Adshares\Common\Domain\ValueObject\AccountId;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

class InsertWalletConfigDisable extends Migration
{
    private const DEFAULT_WALLET_ADDRESS = '0000-00000000-XXXX';

    public function up(): void
    {
        $this->deleteWalletEnablingEntries();

        $configuredAddress = (string)config('app.adshares_wallet_cold_address');
        try {
            $walletAddress = (new AccountId($configuredAddress))->toString();
        } catch (InvalidArgumentException $invalidArgumentException) {
            Log::error(sprintf('[InsertWalletConfigDisable] Provided address (%s) is invalid.', $configuredAddress));

            $walletAddress = self::DEFAULT_WALLET_ADDRESS;
        }
        $isWalletActive = $walletAddress !== self::DEFAULT_WALLET_ADDRESS;

        DB::table('configs')->insert(
            [
                'key' => Config::HOT_WALLET_ADDRESS,
                'value' => $walletAddress,
            ]
        );

        DB::table('configs')->insert(
            [
                'key' => Config::HOT_WALLET_IS_ACTIVE,
                'value' => $isWalletActive,
            ]
        );
    }

    public function down(): void
    {
        $this->deleteWalletEnablingEntries();
    }

    private function deleteWalletEnablingEntries(): void
    {
        DB::table('configs')->whereIn(
            'key',
            [
                Config::HOT_WALLET_ADDRESS,
                Config::HOT_WALLET_IS_ACTIVE,
            ]
        )->delete();
    }
}
