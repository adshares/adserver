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

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Ads\Util\AdsValidator;
use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Jobs\AdsSendOne;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Utilities\AdsUtils;
use Illuminate\Http\JsonResponse;
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

    public function calculateWithdrawal(Request $request): JsonResponse
    {
        $addressFrom = $this->getAdServerAdsAddress();
        if (!AdsValidator::isAccountAddressValid($addressFrom)) {
            Log::error("Invalid ADS address is set: ${addressFrom}");

            return self::json([], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        Validator::make(
            $request->all(),
            [
                self::FIELD_AMOUNT => ['integer', 'min:1'],
                self::FIELD_TO => self::VALIDATOR_RULE_REQUIRED,
            ]
        )->validate();
        $amount = $request->input(self::FIELD_AMOUNT);
        $addressTo = $request->input(self::FIELD_TO);

        if (!AdsValidator::isAccountAddressValid($addressTo)) {
            // invalid input for calculating fee
            return self::json([self::FIELD_ERROR => 'invalid address'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (null === $amount) {
            //calculate max available amount
            $userId = Auth::user()->id;
            $balance = UserLedgerEntry::getBalanceByUserId($userId);
            $amount = AdsUtils::calculateAmount($addressFrom, $addressTo, $balance);
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
     * @return string AdServer address in ADS network
     */
    private function getAdServerAdsAddress(): string
    {
        return config('app.adshares_address');
    }

    public function withdraw(Request $request): JsonResponse
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
        $ul = new UserLedgerEntry();
        $ul->user_id = $userId;
        $ul->amount = -$total;
        $ul->address_from = $addressFrom;
        $ul->address_to = $addressTo;
        $ul->status = UserLedgerEntry::STATUS_PENDING;
        $ul->type = UserLedgerEntry::TYPE_WITHDRAWAL;
        $result = $ul->save();

        if ($result) {
            // add tx to queue: $addressTo is address, $amount is amount, $memo is message (can be null for no message)
            AdsSendOne::dispatch($ul, $addressTo, $amount, $memo);
        }

        return self::json([], $result ? Response::HTTP_NO_CONTENT : Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function depositInfo(): JsonResponse
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

    public function history(Request $request): JsonResponse
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
        $count = UserLedgerEntry::where('user_id', $userId)->count();
        $resp = [];
        $items = [];
        if ($count > 0) {
            foreach (UserLedgerEntry::where('user_id', $userId)->skip($offset)->take($limit)->cursor() as $ledgerItem) {
                $amount = (int)$ledgerItem->amount;
                $date = $ledgerItem->created_at->format(Carbon::RFC7231_FORMAT);
                $status = (int)$ledgerItem->status;
                $type = (int)$ledgerItem->type;
                $txid = $this->getUserLedgerEntryTxid($ledgerItem);
                $address = $this->getUserLedgerEntryAddress($ledgerItem);

                $items[] = [
                    'amount' => $amount,
                    'status' => $status,
                    'type' => $type,
                    'date' => $date,
                    'address' => $address,
                    'txid' => $txid,
                ];
            }
        }
        $resp['limit'] = (int)$limit;
        $resp['offset'] = (int)$offset;
        $resp['items_count'] = count($items);
        $resp['items_count_all'] = $count;
        $resp['items'] = $items;

        return self::json($resp);
    }

    /**
     * @param $ledgerItem
     *
     * @return string
     */
    private function getUserLedgerEntryAddress($ledgerItem): string
    {
        if ((int)$ledgerItem->amount > 0) {
            $address = $ledgerItem->address_to;
        } else {
            $address = $ledgerItem->address_from;
        }

        return $address;
    }

    /**
     * @param $ledgerItem
     *
     * @return null|string
     */
    private function getUserLedgerEntryTxid($ledgerItem): ?string
    {
        $type = (int)$ledgerItem->type;
        $txid = (null !== $ledgerItem->txid
            && ($type === UserLedgerEntry::TYPE_DEPOSIT || $type === UserLedgerEntry::TYPE_WITHDRAWAL))
            ? $ledgerItem->txid : null;

        return $txid;
    }
}
