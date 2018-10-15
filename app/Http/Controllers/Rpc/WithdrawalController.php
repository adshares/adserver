<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Http\Controllers\Rpc;

use Adshares\Adserver\Http\Controllers\Controller;
use Adshares\Adserver\Utilities\AdsUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class WithdrawalController extends Controller
{
    const FIELD_ADDRESS = 'address';

    const FIELD_AMOUNT = 'amount';

    const FIELD_MESSAGE = 'message';

    const FIELD_TO = 'to';
    /**
     * Field is required
     */
    const VALIDATOR_RULE_REQUIRED = 'required';

    /**
     * Checks, if user has enough funds.
     * @param $amount int transfer total amount
     * @return bool true if has enough, false otherwise
     */
    public function hasUserEnoughFunds(int $amount): bool
    {
        // TODO check user account balance
        $balance = 4000000000000000000;
        return $amount <= $balance;
    }

    public function calculateWithdrawal(Request $request)
    {
        Validator::make($request->all(), [
            self::FIELD_AMOUNT => [self::VALIDATOR_RULE_REQUIRED, 'integer', 'min:1'],
            self::FIELD_TO => self::VALIDATOR_RULE_REQUIRED,
        ])->validate();
        $amount = $request->input(self::FIELD_AMOUNT);
        $addressTo = $request->input(self::FIELD_TO);

        $addressFrom = $this->getAdserverAdsAddress();
        $fee = AdsUtils::calculateFee($addressFrom, $addressTo, $amount);

        if ($fee < 0) {
            // invalid input for calculating fee
            return self::json([], 422);
        }
        $total = $amount + $fee;
        $resp = [
            self::FIELD_AMOUNT => $amount,
            'fee' => $fee,
            'total' => $total,
        ];
        return self::json($resp);
    }

    public function withdraw(Request $request)
    {
        Validator::make($request->all(), [
            self::FIELD_AMOUNT => [self::VALIDATOR_RULE_REQUIRED, 'integer', 'min:1'],
            self::FIELD_TO => self::VALIDATOR_RULE_REQUIRED,
        ])->validate();

        $amount = $request->input(self::FIELD_AMOUNT);
        $addressTo = $request->input(self::FIELD_TO);

        $addressFrom = $this->getAdserverAdsAddress();
        $fee = AdsUtils::calculateFee($addressFrom, $addressTo, $amount);

        if ($fee < 0) {
            // invalid input for calculating fee
            return self::json([], 422);
        }
        $total = $amount + $fee;
        if (!$this->hasUserEnoughFunds($total)) {
            return self::json(['error' => 'not enough funds'], 400);
        }

        // TODO add tx to queue: $amount is amount, $addressTo is address

        return self::json([], 204);
    }

    public function depositInfo()
    {
        $user = Auth::user();
        $uuid = $user->uuid;
        /**
         * Address of account on which funds should be deposit
         */
        $address = $this->getAdserverAdsAddress();
        /**
         * Message which should be add to send_one tx
         */
        $message = str_pad($uuid, 64, '0', STR_PAD_LEFT);
        $resp = [
            self::FIELD_ADDRESS => $address,
            self::FIELD_MESSAGE => $message,
        ];
        return self::json($resp);
    }

    /**
     * Returns Adserver address in ADS network.
     *
     * @return \Illuminate\Config\Repository|mixed
     */
    private function getAdserverAdsAddress()
    {
        return config('app.adshares_address');
    }
}
