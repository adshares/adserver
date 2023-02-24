<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

use Adshares\Ads\Util\AdsConverter;
use Adshares\Adserver\Events\ServerEvent;
use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Jobs\AdsSendOne;
use Adshares\Adserver\Jobs\AdsSendOneBatchWithdrawal;
use Adshares\Adserver\Mail\WalletConnectConfirm;
use Adshares\Adserver\Mail\WalletConnected;
use Adshares\Adserver\Mail\WithdrawalApproval;
use Adshares\Adserver\Mail\WithdrawalSuccess;
use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Repository\Common\MySqlQueryBuilder;
use Adshares\Adserver\Services\AdsExchange;
use Adshares\Adserver\Services\NowPayments;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Adserver\Utilities\NonceGenerator;
use Adshares\Adserver\ViewModel\ServerEventType;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Application\Model\Currency;
use Adshares\Common\Application\Service\AdsRpcClient;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Domain\ValueObject\WalletAddress;
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
use Symfony\Component\CssSelector\Exception\InternalErrorException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

use function config;

class WalletController extends Controller
{
    private const APP_CURRENCY = 'app.currency';
    private const CURRENCY_BTC = 'BTC';
    private const FIELD_ADDRESS = 'address';
    private const FIELD_AMOUNT = 'amount';
    private const FIELD_BTC = 'btc';
    private const FIELD_DATE_FROM = 'date_from';
    private const FIELD_DATE_TO = 'date_to';
    private const FIELD_ERROR = 'error';
    private const FIELD_FEE = 'fee';
    private const FIELD_FIAT = 'fiat';
    private const FIELD_LIMIT = 'limit';
    private const FIELD_MEMO = 'memo';
    private const FIELD_MESSAGE = 'message';
    private const FIELD_NOW_PAYMENTS = 'now_payments';
    private const FIELD_NOW_PAYMENTS_URL = 'now_payments_url';
    private const FIELD_OFFSET = 'offset';
    private const FIELD_RECEIVE = 'receive';
    private const FIELD_TO = 'to';
    private const FIELD_TOTAL = 'total';
    private const FIELD_TYPES = 'types';
    private const FIELD_UNWRAPPERS = 'unwrappers';
    private const VALIDATOR_RULE_REQUIRED = 'required';

    public function __construct(private readonly ExchangeRateReader $exchangeRateReader)
    {
    }

    public function withdrawalInfo(): JsonResponse
    {
        $btcInfo = null;
        if (config('app.btc_withdraw')) {
            $fee = config('app.btc_withdraw_fee');
            $rate = 0;
            try {
                $appCurrency = Currency::from(config(self::APP_CURRENCY));
                $rateToAds = (match ($appCurrency) {
                    Currency::ADS => ExchangeRate::ONE($appCurrency),
                    default => $this->exchangeRateReader->fetchExchangeRate(null, $appCurrency->value),
                })->getValue();

                $rate = $this->exchangeRateReader->fetchExchangeRate(null, self::CURRENCY_BTC)->getValue() / $rateToAds;
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

    public function calculateWithdrawal(Request $request, AdsRpcClient $rpcClient): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $addressFrom = $this->getAdServerAdsAddress();
        $fromAccountWallet = null === $user->email;

        $rules = [self::FIELD_AMOUNT => ['integer', 'min:1']];
        if (!$fromAccountWallet) {
            $rules[self::FIELD_TO] = self::VALIDATOR_RULE_REQUIRED;
        }
        Validator::make($request->all(), $rules)->validate();

        if ($fromAccountWallet) {
            $address = $user->wallet_address;
        } else {
            try {
                $address = new WalletAddress(WalletAddress::NETWORK_ADS, $request->input(self::FIELD_TO));
            } catch (InvalidArgumentException $exception) {
                // invalid input for calculating fee
                return self::json([self::FIELD_ERROR => 'Invalid address'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $addressTo = $this->getWalletAdsAddress($rpcClient, $address);
        $amount = $request->input(self::FIELD_AMOUNT);
        $balance = $user->getWalletBalance();
        $maxAmount = AdsUtils::calculateAmount($addressFrom, $addressTo, $balance);
        if (null === $amount) {
            $amount = $maxAmount;
        }

        $adsFee = AdsUtils::calculateFee($addressFrom, $addressTo, $amount);
        if ($amount + $adsFee > $balance) {
            $amount = $maxAmount;
            $adsFee = AdsUtils::calculateFee($addressFrom, $addressTo, $amount);
        }

        $gatewayFee = 0;
        if (WalletAddress::NETWORK_ADS !== $address->getNetwork()) {
            $gatewayFee = $rpcClient->getGatewayFee($address->getNetwork(), $amount, $address->getAddress());
        }

        $resp = [
            self::FIELD_AMOUNT => $amount,
            self::FIELD_FEE => $adsFee,
            self::FIELD_TOTAL => $amount + $adsFee,
            self::FIELD_RECEIVE => max(0, $amount - $gatewayFee),
        ];

        return self::json($resp);
    }

    private function getAdServerAdsAddress(): AccountId
    {
        try {
            return new AccountId(config('app.adshares_address') ?? '');
        } catch (InvalidArgumentException $exception) {
            Log::error(sprintf('Invalid ADS address is set: %s', $exception->getMessage()));
            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'ADS account is not set');
        }
    }

    private function getWalletAdsAddress(AdsRpcClient $rpcClient, WalletAddress $address): string
    {
        if (WalletAddress::NETWORK_ADS === $address->getNetwork()) {
            return $address->getAddress();
        }
        if (WalletAddress::NETWORK_BSC === $address->getNetwork()) {
            $gateway = $rpcClient->getGateway(WalletAddress::NETWORK_BSC);
            return $gateway->getAddress();
        }
        throw new UnprocessableEntityHttpException('Unsupported network');
    }

    private function getWalletAdsMessage(AdsRpcClient $rpcClient, WalletAddress $address): string
    {
        if (WalletAddress::NETWORK_ADS === $address->getNetwork()) {
            return '';
        }
        if (WalletAddress::NETWORK_BSC === $address->getNetwork()) {
            $gateway = $rpcClient->getGateway(WalletAddress::NETWORK_BSC);
            return $gateway->getPrefix() . preg_replace('/^0x/', '', $address->getAddress());
        }
        throw new UnprocessableEntityHttpException('Unsupported network');
    }

    public function confirmWithdrawal(Request $request, AdsExchange $exchange): JsonResponse
    {
        Validator::make($request->all(), ['token' => 'required'])->validate();

        DB::beginTransaction();

        $token = Token::check($request->input('token'), null, Token::EMAIL_APPROVE_WITHDRAWAL);
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

        $appCurrency = Currency::from(config(self::APP_CURRENCY));
        $exchangeRate = match ($appCurrency) {
            Currency::ADS => ExchangeRate::ONE($appCurrency),
            default => $this->exchangeRateReader->fetchExchangeRate(null, $appCurrency->value),
        };
        $amountInClicks = $exchangeRate->toClick($token['payload']['request']['amount']);

        $currency = $token['payload']['request']['currency'] ?? 'ADS';
        if ($currency === self::CURRENCY_BTC) {
            if (
                $exchange->transfer(
                    (float)AdsConverter::clicksToAds($amountInClicks),
                    $currency,
                    $token['payload']['request']['to'],
                    SecureUrl::change(route('withdraw.exchange')),
                    $token['payload']['ledgerEntry']
                )
            ) {
                ServerEvent::dispatch(
                    ServerEventType::UserWithdrawalProcessed,
                    [
                        'amount' => $amountInClicks,
                        'txid' => null,
                        'userId' => $userLedgerEntry->user_id,
                    ]
                );
                Mail::to($userLedgerEntry->user)->queue(
                    new WithdrawalSuccess(
                        $token['payload']['request']['amount'],
                        $currency,
                        0,
                        new WalletAddress(WalletAddress::NETWORK_BTC, $token['payload']['request']['to'])
                    )
                );
            } else {
                $userLedgerEntry->status = UserLedgerEntry::STATUS_NET_ERROR;
                $userLedgerEntry->save();
            }
        } else {
            AdsSendOne::dispatch(
                $userLedgerEntry,
                $token['payload']['request']['to'],
                $amountInClicks,
                $token['payload']['request']['memo'] ?? ''
            );
            $fee = AdsUtils::calculateFee(
                $this->getAdServerAdsAddress(),
                $token['payload']['request']['to'],
                $token['payload']['request']['amount']
            );
            Mail::to($userLedgerEntry->user)->queue(
                new WithdrawalSuccess(
                    $token['payload']['request']['amount'],
                    $appCurrency->value,
                    $fee,
                    new WalletAddress(WalletAddress::NETWORK_ADS, $token['payload']['request']['to'])
                )
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

    public function withdraw(Request $request, AdsRpcClient $rpcClient): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->is_confirmed) {
            return self::json(['message' => 'Confirm account to withdraw funds'], JsonResponse::HTTP_FORBIDDEN);
        }

        if ($request->get('currency') === self::CURRENCY_BTC) {
            return $this->withdrawBtc($request);
        }

        return $this->withdrawAds($request, $rpcClient);
    }

    private function getForeignBSCAddress(): WalletAddress
    {
        try {
            return new WalletAddress('BSC', config('app.foreign_bsc_wallet'));
        } catch (InvalidArgumentException $e) {
            Log::error(sprintf('Invalid BSC address is set: %s', $e->getMessage()));
            throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function withdrawRequestBatch(Request $request, AdsRpcClient $rpcClient): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if(!$user->isModerator()){
            return self::json(['Auth' => 'Only Moderator/Admin can run this API'], JsonResponse::HTTP_FORBIDDEN);
        }

        $addressFrom = $this->getAdServerAdsAddress();
        $address = $this->getForeignBSCAddress();
        $addressTo = $this->getWalletAdsAddress($rpcClient, $address);
        $message = $this->getWalletAdsMessage($rpcClient, $address);
        $batchId = UserLedgerEntry::getNewBatchId();

        $balanceTotal = UserLedgerEntry::getWalletBalanceForForeignUsers();
        $users_balances = UserLedgerEntry::allForeignWalletBalanceIfAny();

        $amount = AdsUtils::calculateAmount($addressFrom, $addressTo, $balanceTotal);
        Log::info("############################Amount is : $amount");
        $adsFee = AdsUtils::calculateFee($addressFrom, $addressTo, $amount);
        $total = $amount + $adsFee;

        if($total < config('app.min_ads_batch_withdrawal')){
            $resp = array('code'=>  9, 'total'=> $total, 'min'=>config('app.min_ads_batch_withdrawal'), 'msg' => 'The value for transfer is less than the defined minimun.');
            return self::json($resp);
        }

        if ($balanceTotal < $total) {
            // not sure it is necessary
            throw new UnprocessableEntityHttpException(sprintf('Insufficient total: %d, fee: %d', $balanceTotal, $adsFee));
        }
        DB::beginTransaction();

        foreach ($users_balances as $item) {
            $user_item = User::fetchById($item['uid']);
            $ledgerEntry = UserLedgerEntry::constructForeignEntry(
                $batchId,
                $user_item->id,
                -$item['share']
            )->addressed($addressFrom, $addressTo);
            if (!$ledgerEntry->save()) {
                DB::rollBack();
                throw new InternalErrorException();
            }
        }
        DB::commit();
        $logMessage = '[WalletController] Request batch withdrawal: Dispatching AdsSendOneBatchWithdrawal with batchId (%s) addressTo (%s) amount (%s) message (%s) users_balances (%s).';
        Log::info(sprintf($logMessage, $batchId, $addressTo, $amount, $message, json_encode($users_balances)));
        AdsSendOneBatchWithdrawal::dispatch(
            $batchId,
            $addressTo,
            $amount,
            $message
        );

        $resp = array('code'=>  0, 'msg' => 'Withdrawal request is submitted successfully.', 'total'=> $amount, 'usersCount' => count($users_balances), 'to' => config('app.foreign_bsc_wallet'), 'batch'=>$batchId);
        return self::json($resp);
    }

    public function withdrawStatusBatch(Request $request, AdsRpcClient $rpcClient): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if(!$user->isModerator()){
            return self::json(['Auth' => 'Only Moderator/Admin can run this API'], JsonResponse::HTTP_FORBIDDEN);
        }
        $batchId = $request->input('batch');
        if (!$batchId){
            return self::json([], Response::HTTP_BAD_REQUEST, ["param 'batch' is missing."]);
        }
        $userLedger = UserLedgerEntry::getFirstRecordByBatchId($batchId);
        if (!$userLedger){
            return self::json([], Response::HTTP_BAD_REQUEST, ["invalid 'batchId'. No record is found."]);
        }
        $status = null;
        $txid = null;
        if (UserLedgerEntry::STATUS_PENDING === $userLedger->status) {
            $status = 'pending';
        }
        if (UserLedgerEntry::STATUS_ACCEPTED === $userLedger->status) {
            $status = 'accepted';
            $txid = $userLedger->txid;
        }

        if (UserLedgerEntry::STATUS_NET_ERROR === $userLedger->status) {
            $status = 'failed';
        }
        if (UserLedgerEntry::STATUS_SYS_ERROR === $userLedger->status) {
            $status = 'failed';
        }
        if ($status === null){
            $status = 'unknown';
        }

        $resp = array('status'=> $status, 'code'=> $userLedger->status, 'batch'=>$batchId);
        if ($txid){
            $resp['txid'] = $txid;
        }
        if (UserLedgerEntry::STATUS_ACCEPTED === $userLedger->status){
            $resp['shares'] = UserLedgerEntry::balancesByBatchId($batchId);
        }
        return self::json($resp);
    }

    private function withdrawAds(Request $request, AdsRpcClient $rpcClient): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $addressFrom = $this->getAdServerAdsAddress();
        $fromAccountWallet = null === $user->email;

        $rules = [self::FIELD_AMOUNT => [self::VALIDATOR_RULE_REQUIRED, 'integer', 'min:1']];
        if (!$fromAccountWallet) {
            $rules[self::FIELD_MEMO] = ['nullable', 'regex:/[0-9a-fA-F]{64}/', 'string'];
            $rules[self::FIELD_TO] = self::VALIDATOR_RULE_REQUIRED;
        }
        Validator::make($request->all(), $rules)->validate();

        if ($fromAccountWallet) {
            $address = $user->wallet_address;
        } else {
            try {
                $address = new WalletAddress(WalletAddress::NETWORK_ADS, $request->input(self::FIELD_TO));
            } catch (InvalidArgumentException $exception) {
                // invalid input for calculating fee
                throw new UnprocessableEntityHttpException('Invalid address');
            }
        }

        $addressTo = $this->getWalletAdsAddress($rpcClient, $address);
        $amount = $request->input(self::FIELD_AMOUNT);
        $adsFee = AdsUtils::calculateFee($addressFrom, $addressTo, $amount);
        $total = $amount + $adsFee;

        if ($user->getWalletBalance() < $total) {
            throw new UnprocessableEntityHttpException();
        }

        $ledgerEntry = UserLedgerEntry::construct(
            $user->id,
            -$total,
            $fromAccountWallet ? UserLedgerEntry::STATUS_PENDING : UserLedgerEntry::STATUS_AWAITING_APPROVAL,
            UserLedgerEntry::TYPE_WITHDRAWAL
        )->addressed($addressFrom, $addressTo);

        $appCurrency = Currency::from(config(self::APP_CURRENCY));
        $exchangeRate = match ($appCurrency) {
            Currency::ADS => ExchangeRate::ONE($appCurrency),
            default => $this->exchangeRateReader->fetchExchangeRate(null, $appCurrency->value),
        };

        DB::beginTransaction();

        if (!$ledgerEntry->save()) {
            DB::rollBack();
            throw new InternalErrorException();
        }

        if (!$fromAccountWallet) {
            $payload = [
                'request' => $request->all(),
                'ledgerEntry' => $ledgerEntry->id,
            ];
            Mail::to($user)->queue(
                new WithdrawalApproval(
                    Token::generate(Token::EMAIL_APPROVE_WITHDRAWAL, $user, $payload)->uuid,
                    $amount,
                    $appCurrency->value,
                    $adsFee,
                    new WalletAddress(WalletAddress::NETWORK_ADS, $addressTo)
                )
            );
        } else {
            AdsSendOne::dispatch(
                $ledgerEntry,
                $addressTo,
                $exchangeRate->toClick($amount),
                $this->getWalletAdsMessage($rpcClient, $address)
            );
        }
        DB::commit();

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    private function withdrawBtc(Request $request): JsonResponse
    {
        $minAmount = config('app.btc_withdraw_min_amount');
        $maxAmount = config('app.btc_withdraw_max_amount');

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
            self::CURRENCY_BTC
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
                self::CURRENCY_BTC,
                0,
                new WalletAddress(WalletAddress::NETWORK_BTC, (string)$addressTo)
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

        $fiatDeposit = config('app.invoice_enabled') ? [
            'minAmount' => config('app.fiat_deposit_min_amount'),
            'maxAmount' => config('app.fiat_deposit_max_amount'),
            'currencies' => config('app.invoice_currencies'),
        ] : null;

        $message = str_pad($uuid, 64, '0', STR_PAD_LEFT);
        $resp = [
            self::FIELD_ADDRESS => $address->toString(),
            self::FIELD_MESSAGE => $message,
            self::FIELD_NOW_PAYMENTS => $nowPayments->info(),
            self::FIELD_UNWRAPPERS => [
                [
                    'chain_id' => 1,
                    'network_name' => 'Ethereum',
                    // phpcs:ignore PHPCompatibility.Numbers.RemovedHexadecimalNumericStrings.Found
                    'contract_address' => '0xcfcEcFe2bD2FED07A9145222E8a7ad9Cf1Ccd22A',
                ],
                [
                    'chain_id' => 56,
                    'network_name' => 'Binance Smart Chain',
                    // phpcs:ignore PHPCompatibility.Numbers.RemovedHexadecimalNumericStrings.Found
                    'contract_address' => '0xcfcEcFe2bD2FED07A9145222E8a7ad9Cf1Ccd22A',
                ]
            ],
            self::FIELD_FIAT => $fiatDeposit,
        ];

        return self::json($resp);
    }

    public function nowPaymentsInit(NowPayments $nowPayments, Request $request): JsonResponse
    {
        /** @var User $user */
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

    public function connectInit(Request $request, AdsRpcClient $rpcClient): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $message = sprintf(
            'Connect your wallet with %s adserver %s',
            config('app.adserver_name'),
            base64_encode(NonceGenerator::get())
        );

        $payload = [
            'request' => $request->all(),
            'message' => $message,
        ];

        return self::json([
            'token' => Token::generate(Token::WALLET_CONNECT, $user, $payload)->uuid,
            'message' => $message,
            'gateways' => [
                'bsc' => $rpcClient->getGateway(WalletAddress::NETWORK_BSC)->toArray()
            ],
        ]);
    }

    public function connect(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $onlyWallet = null === $user->email;
        $address = $this->checkWalletAddress(
            $request->input('token'),
            Token::WALLET_CONNECT,
            $request->all(),
            $user->id
        );

        Validator::make(['wallet_address' => $address], ['wallet_address' => 'required|unique:users'])->validate();

        if ($onlyWallet) {
            DB::beginTransaction();
            try {
                $user->wallet_address = $address;
                $user->saveOrFail();
            } catch (Throwable $exception) {
                DB::rollBack();
                throw $exception;
            }
            DB::commit();

            return self::json($user->toArray());
        }

        Validator::make($request->all(), ['uri' => 'required'])->validate();

        DB::beginTransaction();
        $payload = ['wallet_address' => $address->toString()];
        $token = Token::generate(Token::WALLET_CONNECT_CONFIRM, $user, $payload)->uuid;
        Mail::to($user)->queue(new WalletConnectConfirm($address, $token, $request->input('uri')));
        DB::commit();

        return self::json([], Response::HTTP_NO_CONTENT);
    }

    public function connectConfirm(string $token): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        if (false === ($walletToken = Token::check($token, $user->id, Token::WALLET_CONNECT_CONFIRM))) {
            throw new UnprocessableEntityHttpException('Invalid token');
        }
        try {
            $address = WalletAddress::fromString($walletToken['payload']['wallet_address']);
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException('Invalid wallet address');
        }

        Validator::make(['wallet_address' => $address], ['wallet_address' => 'required|unique:users'])->validate();

        DB::beginTransaction();
        try {
            $user->wallet_address = $address;
            $user->saveOrFail();
            Mail::to($user)->queue(new WalletConnected($address));
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
        DB::commit();

        return self::json($user->toArray());
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
