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

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Ads\Util\AdsValidator;
use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Jobs\AdsSendOne;
use Adshares\Adserver\Mail\WithdrawalApproval;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Repository\Common\MySqlQueryBuilder;
use Adshares\Adserver\Services\AdsExchange;
use Adshares\Adserver\Services\NowPayments;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use stdClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

use function config;

class WalletController extends Controller
{
    private const FIELD_ADDRESS = 'address';

    private const FIELD_AMOUNT = 'amount';

    private const FIELD_DATE_FROM = 'date_from';

    private const FIELD_DATE_TO = 'date_to';

    private const FIELD_ERROR = 'error';

    private const FIELD_FEE = 'fee';

    private const FIELD_LIMIT = 'limit';

    private const FIELD_MEMO = 'memo';

    private const FIELD_MESSAGE = 'message';

    private const FIELD_OFFSET = 'offset';

    private const FIELD_TO = 'to';

    private const FIELD_TOTAL = 'total';

    private const FIELD_TYPES = 'types';

    private const FIELD_BTC = 'btc';

    private const FIELD_NOW_PAYMENTS = 'now_payments';

    private const FIELD_UNWRAPPERS = 'unwrappers';

    private const FIELD_NOW_PAYMENTS_URL = 'now_payments_url';

    private const FIELD_FIAT = 'fiat';

    private const VALIDATOR_RULE_REQUIRED = 'required';

    public function withdrawalInfo(ExchangeRateReader $exchangeRateReader, AdsExchange $adsExchange): JsonResponse
    {
        $btcInfo = null;
        if (config('app.btc_withdraw')) {
            $fee = config('app.btc_withdraw_fee');
            $rate = 0;
            try {
                $exchangeRate = $exchangeRateReader->fetchExchangeRate(null, 'BTC')->toArray();
                $rate = (float)$exchangeRate['value'];
            } catch (ExchangeRateNotAvailableException $exception) {
                Log::error(sprintf('[NowPayments] Cannot fetch exchange rate: %s', $exception->getMessage()));
            }

            $btcInfo = [
                'minAmount' => config('app.btc_withdraw_min_amount'),
                'maxAmount' => config('app.btc_withdraw_max_amount'),
                'exchangeRate' => $rate / (1 - $fee),
            ];
        }

        $resp = [
            self::FIELD_BTC => $btcInfo,
        ];

        return self::json($resp);
    }

    public function calculateWithdrawal(Request $request): JsonResponse
    {
        $addressFrom = $this->getAdServerAdsAddress();

        if (!AdsValidator::isAccountAddressValid($addressFrom)) {
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
            $balance = Auth::user()->getWalletBalance();
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

    private function getAdServerAdsAddress(): AccountId
    {
        try {
            return new AccountId(config('app.adshares_address'));
        } catch (InvalidArgumentException $e) {
            Log::error(sprintf('Invalid ADS address is set: %s', $e->getMessage()));

            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function confirmWithdrawal(Request $request, AdsExchange $exchange): JsonResponse
    {
        Validator::make($request->all(), ['token' => 'required'])->validate();

        DB::beginTransaction();

        $token = Token::check($request->input('token'));
        if (false === $token || Auth::user()->id !== (int)$token['user_id']) {
            DB::rollBack();

            return self::json([], Response::HTTP_NOT_FOUND);
        }

        $userLedgerEntry = UserLedgerEntry::find($token['payload']['ledgerEntry']);

        if (
            UserLedgerEntry::TYPE_WITHDRAWAL !== $userLedgerEntry->type
            || UserLedgerEntry::STATUS_AWAITING_APPROVAL !== $userLedgerEntry->status
        ) {
            throw new UnprocessableEntityHttpException('Payment already approved');
        }

        $userLedgerEntry->status = UserLedgerEntry::STATUS_PENDING;
        $userLedgerEntry->save();

        $currency = $token['payload']['request']['currency'] ?? 'ADS';
        if ($currency === 'BTC') {
            if (
                false === $exchange->transfer(
                    $token['payload']['request']['amount'],
                    $currency,
                    $token['payload']['request']['to'],
                    SecureUrl::change(route('withdraw.exchange')),
                    $token['payload']['ledgerEntry']
                )
            ) {
                $userLedgerEntry->status = UserLedgerEntry::STATUS_NET_ERROR;
                $userLedgerEntry->save();
            }
        } else {
            AdsSendOne::dispatch(
                $userLedgerEntry,
                $token['payload']['request']['to'],
                $token['payload']['request']['amount'],
                $token['payload']['request']['memo'] ?? ''
            );
        }

        DB::commit();

        return self::json();
    }

    public function cancelWithdrawal(UserLedgerEntry $entry): JsonResponse
    {
        if (
            Auth::user()->id !== $entry->user_id
            || UserLedgerEntry::TYPE_WITHDRAWAL !== $entry->type
            || UserLedgerEntry::STATUS_AWAITING_APPROVAL !== $entry->status
        ) {
            throw new NotFoundHttpException();
        }

        $entry->status = UserLedgerEntry::STATUS_CANCELED;
        $entry->save();

        return self::json();
    }

    public function withdraw(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->is_confirmed) {
            return self::json(['message' => 'Confirm account to withdraw funds'], JsonResponse::HTTP_FORBIDDEN);
        }

        if ($request->get('currency') === 'BTC') {
            return $this->withdrawBtc($request);
        }

        return $this->withdrawAds($request);
    }

    private function withdrawAds(Request $request): JsonResponse
    {
        $addressFrom = $this->getAdServerAdsAddress();

        Validator::make(
            $request->all(),
            [
                self::FIELD_AMOUNT => [self::VALIDATOR_RULE_REQUIRED, 'integer', 'min:1'],
                self::FIELD_MEMO => ['nullable', 'regex:/[0-9a-fA-F]{64}/', 'string'],
                self::FIELD_TO => self::VALIDATOR_RULE_REQUIRED,
            ]
        )->validate();

        try {
            $addressTo = new AccountId($request->input(self::FIELD_TO));
        } catch (InvalidArgumentException $e) {
            return self::json([], Response::HTTP_UNPROCESSABLE_ENTITY, $e->getMessage());
        }

        $amount = (int)$request->input(self::FIELD_AMOUNT);
        $fee = AdsUtils::calculateFee($addressFrom, $addressTo, $amount);

        $total = $amount + $fee;

        /** @var User $user */
        $user = Auth::user();

        if ($user->getWalletBalance() < $total) {
            return self::json([], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $ledgerEntry = UserLedgerEntry::construct(
            $user->id,
            -$total,
            UserLedgerEntry::STATUS_AWAITING_APPROVAL,
            UserLedgerEntry::TYPE_WITHDRAWAL
        )->addressed($addressFrom, $addressTo);

        DB::beginTransaction();

        if (!$ledgerEntry->save()) {
            return self::json([], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $payload = [
            'request' => $request->all(),
            'ledgerEntry' => $ledgerEntry->id,
        ];

        Mail::to($user)->queue(
            new WithdrawalApproval(
                Token::generate(Token::EMAIL_APPROVE_WITHDRAWAL, $user, $payload)->uuid,
                $amount,
                'ADS',
                $fee,
                $addressTo->toString()
            )
        );

        DB::commit();

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    private function withdrawBtc(Request $request): JsonResponse
    {
        $minAmount = 1e11 * config('app.btc_withdraw_min_amount');
        $maxAmount = 1e11 * config('app.btc_withdraw_max_amount');

        Validator::make(
            $request->all(),
            [
                self::FIELD_AMOUNT => [self::VALIDATOR_RULE_REQUIRED, 'integer', "min:$minAmount", "max:$maxAmount"],
                self::FIELD_TO => [
                    self::VALIDATOR_RULE_REQUIRED,
                    'regex:/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/',
                    'string',
                ],
            ]
        )->validate();

        $amount = (int)$request->input(self::FIELD_AMOUNT);
        $addressTo = $request->input(self::FIELD_TO);

        /** @var User $user */
        $user = Auth::user();

        if ($user->getWalletBalance() < $amount) {
            return self::json([], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $ledgerEntry = UserLedgerEntry::construct(
            $user->id,
            -$amount,
            UserLedgerEntry::STATUS_AWAITING_APPROVAL,
            UserLedgerEntry::TYPE_WITHDRAWAL,
            null,
            'BTC'
        )->addressed(null, $addressTo);

        DB::beginTransaction();

        if (!$ledgerEntry->save()) {
            return self::json([], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $payload = [
            'request' => $request->all(),
            'ledgerEntry' => $ledgerEntry->id,
        ];

        Mail::to($user)->queue(
            new WithdrawalApproval(
                Token::generate(Token::EMAIL_APPROVE_WITHDRAWAL, $user, $payload)->uuid,
                $amount,
                'BTC',
                0,
                $addressTo
            )
        );

        DB::commit();

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function withdrawExchange(
        AdsExchange $exchange,
        Request $request
    ): Response {
        $headerHash = $request->headers->get('x-api-hash', '');
        $params = $request->json()->all();
        $hash = $exchange->hash($params);

        if (!hash_equals($hash, $headerHash)) {
            Log::warning(sprintf('[Exchange] Header hash (%s) mismatched params hash (%s)', $headerHash, $hash));

            return response()->noContent(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $paymentId = $params['paymentId'] ?? -1;
        $userLedgerEntry = UserLedgerEntry::find($paymentId);
        if ($userLedgerEntry === null) {
            Log::warning(sprintf('[Exchange] Cannot find user ledger entry (%s)', $paymentId));

            return response()->noContent(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $status = $params['status'] ?? null;
        $userLedgerEntry->status =
            $status === 'success' ? UserLedgerEntry::STATUS_ACCEPTED : UserLedgerEntry::STATUS_SYS_ERROR;
        $userLedgerEntry->save();

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }

    public function depositInfo(NowPayments $nowPayments): JsonResponse
    {
        $user = Auth::user();
        $uuid = $user->uuid;

        $address = $this->getAdServerAdsAddress();

        $fiatDeposit = Config::isTrueOnly(Config::INVOICE_ENABLED) ? [
            'minAmount' => config('app.fiat_deposit_min_amount'),
            'maxAmount' => config('app.fiat_deposit_max_amount'),
            'currencies' => explode(',', Config::fetchStringOrFail(Config::INVOICE_CURRENCIES)),
        ] : null;

        $message = str_pad($uuid, 64, '0', STR_PAD_LEFT);
        $resp = [
            self::FIELD_ADDRESS      => $address->toString(),
            self::FIELD_MESSAGE      => $message,
            self::FIELD_NOW_PAYMENTS => $nowPayments->info(),
            self::FIELD_UNWRAPPERS   => [
                [
                    'chain_id'    => 1,
                    'network_name'    => 'Ethereum',
                    // phpcs:ignore PHPCompatibility.Miscellaneous.ValidIntegers.HexNumericStringFound
                    'contract_address' => '0xcfcEcFe2bD2FED07A9145222E8a7ad9Cf1Ccd22A',
                ],
                [
                    'chain_id'    => 56,
                    'network_name'    => 'Binance Smart Chain',
                    // phpcs:ignore PHPCompatibility.Miscellaneous.ValidIntegers.HexNumericStringFound
                    'contract_address' => '0xcfcEcFe2bD2FED07A9145222E8a7ad9Cf1Ccd22A',
                ]
            ],
            self::FIELD_FIAT => $fiatDeposit,
        ];

        return self::json($resp);
    }

    public function nowPaymentsInit(NowPayments $nowPayments, Request $request): JsonResponse
    {
        $user = Auth::user();
        $amount = (float)$request->get('amount', 10);

        $resp = [
            self::FIELD_NOW_PAYMENTS_URL => $nowPayments->getPaymentUrl($user, $amount),
        ];

        return self::json($resp);
    }

    public function nowPaymentsNotify(string $uuid, NowPayments $nowPayments, Request $request): Response
    {
        $headerHash = $request->headers->get('x-nowpayments-sig');
        $params = $request->request->all();
        $hash = $nowPayments->hash($params);

        if (!hash_equals($hash, $headerHash)) {
            Log::warning(sprintf('[NowPayments] Header hash (%s) mismatched params hash (%s)', $headerHash, $hash));

            return response()->noContent(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = User::fetchByUuid($uuid);
        if ($user === null) {
            Log::warning(sprintf('[NowPayments] Cannot find user (%s)', $uuid));

            return response()->noContent(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $nowPayments->notify($user, $params);

        return response()->noContent($result ? Response::HTTP_NO_CONTENT : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function nowPaymentsExchange(
        string $uuid,
        AdsExchange $exchange,
        NowPayments $nowPayments,
        Request $request
    ): Response {
        $headerHash = $request->headers->get('x-api-hash', '');
        $params = $request->json()->all();
        $hash = $exchange->hash($params);

        if (!hash_equals($hash, $headerHash)) {
            Log::warning(sprintf('[Exchange] Header hash (%s) mismatched params hash (%s)', $headerHash, $hash));

            return response()->noContent(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = User::fetchByUuid($uuid);
        if ($user === null) {
            Log::warning(sprintf('[Exchange] Cannot find user (%s)', $uuid));

            return response()->noContent(Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $status = $params['status'] ?? null;
        if ($status === 'success') {
            if (false === ($result = $nowPayments->exchange($user, $params))) {
                return response()->noContent(Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        } else {
            Log::warning(sprintf('[Exchange] Cannot exchange deposit (%s): %s', $uuid, $status));
        }

        return response()->noContent(Response::HTTP_NO_CONTENT);
    }

    public function history(Request $request): JsonResponse
    {
        Validator::make(
            $request->all(),
            [
                self::FIELD_TYPES => 'array',
                self::FIELD_TYPES . '.*' => ['integer', Rule::in(UserLedgerEntry::ALLOWED_TYPE_LIST)],
                self::FIELD_DATE_FROM => 'date_format:' . DateTimeInterface::ATOM,
                self::FIELD_DATE_TO => 'date_format:' . DateTimeInterface::ATOM,
                self::FIELD_LIMIT => ['integer', 'min:1'],
                self::FIELD_OFFSET => ['integer', 'min:0'],
            ]
        )->validate();

        $types = $request->input(self::FIELD_TYPES, []);
        $from =
            null !== $request->input(self::FIELD_DATE_FROM) ? DateTime::createFromFormat(
                DateTimeInterface::ATOM,
                $request->input(self::FIELD_DATE_FROM)
            ) : null;
        $to =
            null !== $request->input(self::FIELD_DATE_TO) ? DateTime::createFromFormat(
                DateTimeInterface::ATOM,
                $request->input(self::FIELD_DATE_TO)
            ) : null;
        $limit = $request->input(self::FIELD_LIMIT, 10);
        $offset = $request->input(self::FIELD_OFFSET, 0);

        $userId = Auth::user()->id;

        $changeDbSessionTimezone = null !== $from;
        if ($changeDbSessionTimezone) {
            $dateTimeZone = new DateTimeZone($from->format('O'));
            $this->setDbSessionTimezone($dateTimeZone);
        } else {
            $dateTimeZone = null;
        }

        $builder = UserLedgerEntry::getBillingHistoryBuilder($userId, $types, $from, $to);
        $count = $builder->getCountForPagination();
        $resp = [];
        $items = [];
        if ($count > 0) {
            $userLedgerItems = $builder->orderBy('created_at', 'desc')->skip($offset)->take($limit)->get();

            /** @var stdClass $ledgerItem */
            foreach ($userLedgerItems as $ledgerItem) {
                $date = MySqlQueryBuilder::convertMySqlDateToDateTime($ledgerItem->created_at, $dateTimeZone)
                    ->format(DATE_ATOM);

                $items[] = [
                    'amount' => (int)$ledgerItem->amount,
                    'status' => (int)$ledgerItem->status,
                    'type' => (int)$ledgerItem->type,
                    'date' => $date,
                    'address' => $this->getUserLedgerEntryAddress($ledgerItem),
                    'txid' => $ledgerItem->txid,
                    'id' => (int)$ledgerItem->id,
                ];
            }
        }

        if ($changeDbSessionTimezone) {
            $this->unsetDbSessionTimeZone();
        }

        $resp['limit'] = (int)$limit;
        $resp['offset'] = (int)$offset;
        $resp['items_count'] = count($items);
        $resp['items_count_all'] = $count;
        $resp['items'] = $items;

        return self::json($resp);
    }

    private function setDbSessionTimezone(DateTimeZone $dateTimeZone): void
    {
        if (DB::isMySql()) {
            DB::statement('SET @tmp_time_zone = (SELECT @@session.time_zone)');
            DB::statement(sprintf("SET time_zone = '%s'", $dateTimeZone->getName()));
        }
    }

    private function unsetDbSessionTimeZone(): void
    {
        if (DB::isMySql()) {
            DB::statement('SET time_zone = (SELECT @tmp_time_zone)');
        }
    }

    private function getUserLedgerEntryAddress(stdClass $ledgerItem): ?string
    {
        if ((int)$ledgerItem->amount > 0) {
            return $ledgerItem->address_from;
        }

        return $ledgerItem->address_to;
    }
}
