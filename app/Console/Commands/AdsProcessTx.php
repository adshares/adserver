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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Ads\AdsClient;
use Adshares\Ads\Driver\CommandError;
use Adshares\Ads\Entity\Transaction\SendManyTransaction;
use Adshares\Ads\Entity\Transaction\SendManyTransactionWire;
use Adshares\Ads\Entity\Transaction\SendOneTransaction;
use Adshares\Ads\Exception\CommandException;
use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Mail\CampaignResume;
use Adshares\Adserver\Mail\DepositProcessed;
use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Services\Common\AdsLogReader;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class AdsProcessTx extends BaseCommand
{
    public const EXIT_CODE_SUCCESS = 0;

    public const EXIT_CODE_CANNOT_GET_BLOCK_IDS = 1;

    public const EXIT_CODE_LOCKED = 2;

    protected $signature = 'ads:process-tx';

    protected $description = 'Fetches and processes incoming transactions';

    /** @var string */
    private $adServerAddress;

    /** @var AdsLogReader */
    private $adsLogReader;

    /** @var ExchangeRateReader */
    private $exchangeRateReader;

    /** @var AdsClient */
    private $adsClient;

    public function __construct(
        Locker $locker,
        AdsLogReader $adsLogReader,
        ExchangeRateReader $exchangeRateReader,
        AdsClient $adsClient
    ) {
        parent::__construct($locker);
        $this->adServerAddress = (string)config('app.adshares_address');
        $this->adsLogReader = $adsLogReader;
        $this->exchangeRateReader = $exchangeRateReader;
        $this->adsClient = $adsClient;
    }

    public function handle(): int
    {
        if (!$this->lock()) {
            $this->info('Command ' . $this->getName() . ' already running');

            return self::EXIT_CODE_LOCKED;
        }

        $this->info('Start command ' . $this->getName());

        try {
            $transactionCount = $this->adsLogReader->parseLog();
            $this->info("Number of added transactions: ${transactionCount}");
        } catch (CommandException $commandException) {
            $this->error('Cannot get log');
        }

        try {
            $this->updateBlockIds($this->adsClient);
        } catch (CommandException $exc) {
            $code = $exc->getCode();
            $message = $exc->getMessage();
            $this->error("Cannot update blocks due to CommandException (${code})(${message})");

            $this->info('Premature finish processing incoming txs.');

            return self::EXIT_CODE_CANNOT_GET_BLOCK_IDS;
        }

        $adsPayments = AdsPayment::fetchByStatus(AdsPayment::STATUS_NEW);

        foreach ($adsPayments as $adsPayment) {
            try {
                DB::beginTransaction();

                $this->handleDbTx($adsPayment);

                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();

                throw $e;
            }
        }

        $this->info('Finish processing incoming txs');

        return self::EXIT_CODE_SUCCESS;
    }

    private function updateBlockIds(AdsClient $adsClient): void
    {
        $attempt = 0;
        $attemptMax = 5;
        while (true) {
            try {
                $response = $adsClient->getBlockIds();
                $updatedBlocks = $response->getUpdatedBlocks();
                $this->info("Updated blocks: ${updatedBlocks}");
                if ($updatedBlocks === 0) {
                    break;
                }
            } catch (CommandException $exc) {
                if (++$attempt < $attemptMax && CommandError::GET_SIGNATURE_UNAVAILABLE === $exc->getCode()) {
                    // try again after 3 seconds sleep
                    sleep(3);
                } else {
                    throw $exc;
                }
            }
        }
    }

    private function handleDbTx(AdsPayment $adsPayment): void
    {
        try {
            $transactionId = $adsPayment->txid;
            $transaction = $this->adsClient->getTransaction($transactionId)->getTxn();
        } catch (CommandException $commandException) {
            $code = $commandException->getCode();
            $message = $commandException->getMessage();
            $this->info(
                "Cannot get transaction [$transactionId] data due to CommandException (${code})(${message})"
            );

            return;
        }

        $type = $transaction->getType();
        switch ($type) {
            case 'send_many':
                /** @var $transaction SendManyTransaction */
                $this->handleSendManyTx($adsPayment, $transaction);
                break;

            case 'send_one':
                /** @var $transaction SendOneTransaction */
                $this->handleSendOneTx($adsPayment, $transaction);
                break;

            default:
                $adsPayment->status = AdsPayment::STATUS_INVALID;
                $adsPayment->save();
                break;
        }
    }

    private function handleSendManyTx(AdsPayment $adsPayment, SendManyTransaction $transaction): void
    {
        $adsPayment->tx_time = $transaction->getTime();

        if ($this->isSendManyTransactionTargetValid($transaction)) {
            $this->handleReservedTx($adsPayment);
        } else {
            $adsPayment->status = AdsPayment::STATUS_INVALID;
            $adsPayment->save();
        }
    }

    private function isSendManyTransactionTargetValid(SendManyTransaction $transaction): bool
    {
        if ($transaction->getWireCount() > 0) {
            foreach ($transaction->getWires() as $wire) {
                /** @var $wire SendManyTransactionWire */
                if ($wire->getTargetAddress() === $this->adServerAddress) {
                    return true;
                }
            }
        }

        return false;
    }

    private function handleReservedTx(AdsPayment $adsPayment): void
    {
        $adsPayment->status = $this->checkIfColdWalletTransaction($adsPayment)
            ? AdsPayment::STATUS_TRANSFER_FROM_COLD_WALLET
            : AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE;
        $adsPayment->save();
    }

    private function checkIfColdWalletTransaction(AdsPayment $adsPayment): bool
    {
        return $adsPayment->address === config('app.adshares_wallet_cold_address');
    }

    private function handleSendOneTx(AdsPayment $adsPayment, SendOneTransaction $transaction): void
    {
        $adsPayment->tx_time = $transaction->getTime();

        $targetAddr = $transaction->getTargetAddress();

        if ($targetAddr === $this->adServerAddress) {
            $message = $transaction->getMessage();
            $uuid = $this->extractUuidFromMessage($message);
            $user = User::fetchByUuid($uuid);

            if (null === $user) {
                $this->handleReservedTx($adsPayment);
            } else {
                DB::beginTransaction();

                $senderAddress = $transaction->getSenderAddress();
                $amount = $transaction->getAmount();

                $ledgerEntry = UserLedgerEntry::construct(
                    $user->id,
                    $amount,
                    UserLedgerEntry::STATUS_ACCEPTED,
                    UserLedgerEntry::TYPE_DEPOSIT
                )->addressed($senderAddress, $targetAddr)
                    ->processed($adsPayment->txid);

                $adsPayment->status = AdsPayment::STATUS_USER_DEPOSIT;

                $ledgerEntry->save();
                $adsPayment->save();

                Mail::to($user)->queue(new DepositProcessed($amount));

                try {
                    $reactivatedCount = $this->reactivateSuspendedCampaigns($user);
                    if ($reactivatedCount > 0) {
                        Log::debug("We restarted all suspended campaigns owned by user [{$user->id}].");
                        Mail::to($user)->queue(new CampaignResume());
                    }
                } catch (InvalidArgumentException $exception) {
                    Log::debug("Notify user [{$user->id}] that we cannot restart campaigns.");
                }

                DB::commit();
            }
        } else {
            $adsPayment->status = AdsPayment::STATUS_INVALID;
            $adsPayment->save();
        }
    }

    private function extractUuidFromMessage(string $message): string
    {
        return substr($message, -32);
    }

    private function reactivateSuspendedCampaigns(User $user): int
    {
        $exchangeRate = $this->exchangeRateReader->fetchExchangeRate();

        $balance = $user->getBalance();
        $campaigns = $user->campaigns;

        $requiredBudget = $campaigns->filter(function (Campaign $campaign) {
            return $campaign->status === Campaign::STATUS_ACTIVE || $campaign->status === Campaign::STATUS_SUSPENDED;
        })->sum('budget');

        $requiredBalance = $exchangeRate->toClick($requiredBudget);

        if ($balance >= $requiredBalance) {
            $suspendedCampaigns = $campaigns->filter(static function (Campaign $campaign) {
                return $campaign->status === Campaign::STATUS_SUSPENDED;
            });

            $suspendedCampaigns->each(static function (Campaign $campaign) use ($exchangeRate) {
                if ($campaign->changeStatus(Campaign::STATUS_ACTIVE, $exchangeRate)) {
                    $campaign->save();
                }
            });

            return $suspendedCampaigns->count();
        }

        return 0;
    }
}
