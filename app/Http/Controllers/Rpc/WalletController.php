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

use Adshares\Ads\Util\AdsValidator;
use Adshares\Adserver\Http\Controllers\Controller;
use Adshares\Adserver\Models\UserLedger;
use Adshares\Adserver\Utilities\AdsUtils;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WalletController extends Controller
{
    const FIELD_ADDRESS = 'address';
    const FIELD_AMOUNT = 'amount';
    const FIELD_ERROR = 'error';
    const FIELD_FEE = 'fee';
    const FIELD_MESSAGE = 'message';
    const FIELD_TO = 'to';
    const FIELD_TOTAL = 'total';
    const FIELD_MEMO = 'memo';
    const VALIDATOR_RULE_REQUIRED = 'required';

    public function calculateWithdrawal(Request $request)
    {
        $addressFrom = $this->getAdServerAdsAddress();
        if (!AdsValidator::isAccountAddressValid($addressFrom)) {
            Log::error("Invalid ADS address is set: ${addressFrom}");
            return self::json([], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        Validator::make($request->all(), [
            self::FIELD_AMOUNT => [self::VALIDATOR_RULE_REQUIRED, 'integer', 'min:1'],
            self::FIELD_TO => self::VALIDATOR_RULE_REQUIRED,
        ])->validate();
        $amount = $request->input(self::FIELD_AMOUNT);
        $addressTo = $request->input(self::FIELD_TO);

        if (!AdsValidator::isAccountAddressValid($addressTo)) {
            // invalid input for calculating fee
            return self::json([self::FIELD_ERROR => 'invalid address'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $fee = AdsUtils::calculateFee($addressFrom, $addressTo, $amount);

        $total = $amount + $fee;
        $resp = [
            self::FIELD_AMOUNT => $amount,
            self::FIELD_FEE => $fee,
            self::FIELD_TOTAL => $total,
        ];

        return self::json($resp);
    }

    public function withdraw(Request $request)
    {
        $addressFrom = $this->getAdServerAdsAddress();
        if (!AdsValidator::isAccountAddressValid($addressFrom)) {
            Log::error("Invalid ADS address is set: ${addressFrom}");
            return self::json([], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        Validator::make($request->all(), [
            self::FIELD_AMOUNT => [self::VALIDATOR_RULE_REQUIRED, 'integer', 'min:1'],
            self::FIELD_TO => self::VALIDATOR_RULE_REQUIRED,
        ])->validate();
        $amount = $request->input(self::FIELD_AMOUNT);
        $addressTo = $request->input(self::FIELD_TO);
        //TODO validate memo
        $memo = $request->input(self::FIELD_MEMO);

        if (!AdsValidator::isAccountAddressValid($addressTo)) {
            // invalid input for calculating fee
            return self::json([self::FIELD_ERROR => 'invalid address'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $fee = AdsUtils::calculateFee($addressFrom, $addressTo, $amount);

        $total = $amount + $fee;
        if (!$this->hasUserEnoughFunds($total)) {
            return self::json([self::FIELD_ERROR => 'not enough funds'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // TODO add tx to queue: $amount is amount, $addressTo is address

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function depositInfo()
    {
        $user = Auth::user();
        $uuid = $user->uuid;
        /**
         * Address of account on which funds should be deposit
         */
        $address = $this->getAdServerAdsAddress();
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
     * Checks, if user has enough funds.
     *
     * @param $amount int transfer total amount
     *
     * @return bool true if has enough, false otherwise
     */
    private function hasUserEnoughFunds(int $amount): bool
    {
        // check user account balance
        $user = Auth::user();
        $balance = UserLedger::where('users_id', $user->id)->sum('amount');

        return $amount <= $balance;
    }

    /**
     * Returns AdServer address in ADS network.
     *
     * @return \Illuminate\Config\Repository|mixed
     */
    private function getAdServerAdsAddress()
    {
        return config('app.adshares_address');
    }
}
