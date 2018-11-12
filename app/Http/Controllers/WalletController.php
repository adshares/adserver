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

namespace Adshares\Adserver\Http\Controllers;

use Adshares\Ads\Util\AdsConverter;
use Adshares\Ads\Util\AdsValidator;
use Adshares\Adserver\Jobs\AdsSendOne;
use Adshares\Adserver\Models\UserLedger;
use Adshares\Adserver\Utilities\AdsUtils;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WalletController extends Controller
{
    const FIELD_ADDRESS = 'address';
    const FIELD_AMOUNT = 'amount';
    const FIELD_ERROR = 'error';
    const FIELD_FEE = 'fee';
    const FIELD_LIMIT = 'limit';
    const FIELD_MEMO = 'memo';
    const FIELD_MESSAGE = 'message';
    const FIELD_OFFSET = 'offset';
    const FIELD_TO = 'to';
    const FIELD_TOTAL = 'total';
    const VALIDATOR_RULE_REQUIRED = 'required';

    public function calculateWithdrawal(Request $request)
    {
        $addressFrom = $this->getAdServerAdsAddress();
        if (!AdsValidator::isAccountAddressValid($addressFrom)) {
            Log::error("Invalid ADS address is set: ${addressFrom}");

            return self::json([], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        Validator::make(
            $request->all(),
            [
                self::FIELD_AMOUNT => [self::VALIDATOR_RULE_REQUIRED, 'integer', 'min:1'],
                self::FIELD_TO => self::VALIDATOR_RULE_REQUIRED,
            ]
        )->validate();
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

    /**
     * Returns AdServer address in ADS network.
     *
     * @return \Illuminate\Config\Repository|mixed
     */
    private function getAdServerAdsAddress()
    {
        return config('app.adshares_address');
    }

    public function withdraw(Request $request)
    {
        $addressFrom = $this->getAdServerAdsAddress();
        if (!AdsValidator::isAccountAddressValid($addressFrom)) {
            Log::error("Invalid ADS address is set: ${addressFrom}");

            return self::json([], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        Validator::make(
            $request->all(),
            [
                self::FIELD_AMOUNT => [self::VALIDATOR_RULE_REQUIRED, 'integer', 'min:1'],
                self::FIELD_MEMO => ['nullable', 'regex:/[0-9a-fA-F]{64}/', 'string'],
                self::FIELD_TO => self::VALIDATOR_RULE_REQUIRED,
            ]
        )->validate();

        $amount = $request->input(self::FIELD_AMOUNT);
        $addressTo = $request->input(self::FIELD_TO);
        $memo = $request->input(self::FIELD_MEMO);

        if (!AdsValidator::isAccountAddressValid($addressTo)) {
            // invalid input for calculating fee
            return self::json([self::FIELD_ERROR => 'invalid address'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $fee = AdsUtils::calculateFee($addressFrom, $addressTo, $amount);
        $total = $amount + $fee;

        $userId = Auth::user()->id;
        $ul = new UserLedger();
        $ul->user_id = $userId;
        $ul->amount = -$total;
        $ul->address_from = $addressFrom;
        $ul->address_to = $addressTo;
        $ul->status = UserLedger::STATUS_PENDING;
        $result = $ul->save();

        if ($result) {
            // add tx to queue: $addressTo is address, $amount is amount, $memo is message (can be null for no message)
            AdsSendOne::dispatch($ul, $addressTo, $amount, $memo);
        }

        return self::json([], $result ? Response::HTTP_NO_CONTENT : Response::HTTP_INTERNAL_SERVER_ERROR);
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

    public function history(Request $request)
    {
        Validator::make(
            $request->all(),
            [
                self::FIELD_LIMIT => ['integer', 'min:1'],
                self::FIELD_OFFSET => ['integer', 'min:0'],
            ]
        )->validate();

        $limit = $request->input(self::FIELD_LIMIT, 10);
        $offset = $request->input(self::FIELD_OFFSET, 0);

        $userId = Auth::user()->id;
        $resp = [];
        foreach (UserLedger::where('user_id', $userId)->skip($offset)->take($limit)->cursor() as $ul) {
            $amount = AdsConverter::clicksToAds($ul->amount);
            $date = $ul->created_at->format(Carbon::RFC7231_FORMAT);
            $txid = $ul->txid;
            if (null !== $txid) {
                $link = self::getTransactionLink($txid);
            } else {
                $link = '-';
            }
            if ($amount > 0) {
                $address = $ul->address_to;
            } else {
                $address = $ul->address_from;
            }
            $entry = [
                'status' => $amount,
                'date' => $date,
                'address' => $address,
                'link' => $link,
            ];
            array_push($resp, $entry);
        }

        return self::json($resp);
    }

    /**
     * Returns link to transaction data.
     *
     * @param $txid string transaction id
     *
     * @return string link to transaction
     */
    private static function getTransactionLink(string $txid): string
    {
        $adsOperator = config('app.ads_operator_url');

        return "$adsOperator/blockexplorer/transactions/$txid";
    }
}
