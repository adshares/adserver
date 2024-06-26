<?php

/**
 * Copyright (c) 2018-2024 Adshares sp. z o.o.
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
use Adshares\Ads\Entity\Transaction\SendOneTransaction;
use Adshares\Ads\Exception\CommandException;
use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Events\ServerEvent;
use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Mail\CampaignResume;
use Adshares\Adserver\Mail\DepositProcessed;
use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\JoiningFee;
use Adshares\Adserver\Models\SspHost;
use Adshares\Adserver\Models\TurnoverEntry;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Services\Common\AdsLogReader;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Adserver\ViewModel\ServerEventType;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Application\Model\Currency;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Supply\Domain\ValueObject\TurnoverEntryType;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdsProcessTx extends BaseCommand
{
    public const EXIT_CODE_SUCCESS = 0;
    public const EXIT_CODE_CANNOT_GET_BLOCK_IDS = 1;
    public const EXIT_CODE_LOCKED = 2;

    protected $signature = 'ads:process-tx';
    protected $description = 'Fetches and processes incoming transactions';

    public function __construct(
        Locker $locker,
        private readonly AdsLogReader $adsLogReader,
        private readonly ExchangeRateReader $exchangeRateReader,
        private readonly AdsClient $adsClient
    ) {
        parent::__construct($locker);
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
            $this->info(sprintf('Number of added transactions: %d', $transactionCount));
        } catch (CommandException $commandException) {
            $this->error('Cannot get log');
        }

        try {
            $this->updateBlockIds($this->adsClient);
        } catch (CommandException $exception) {
            $this->error(
                sprintf(
                    'Cannot update blocks due to CommandException (%s)(%s)',
                    $exception->getCode(),
                    $exception->getMessage()
                )
            );
            $this->info('Premature finish processing incoming txs.');

            return self::EXIT_CODE_CANNOT_GET_BLOCK_IDS;
        }

        $adsPayments = AdsPayment::fetchByStatus(AdsPayment::STATUS_NEW);

        foreach ($adsPayments as $adsPayment) {
            try {
                DB::beginTransaction();
                $this->handleDbTx($adsPayment);
                DB::commit();
            } catch (Exception $exception) {
                Log::error(
                    sprintf(
                        'Exception during processing incoming payment (id=%d): (%s)',
                        $adsPayment->id,
                        $exception->getMessage()
                    )
                );
                DB::rollBack();
                throw $exception;
            }
        }

        $this->info('Finish processing incoming txs');
        ServerEvent::dispatch(ServerEventType::IncomingTransactionProcessed);

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
                $this->info(sprintf('Updated blocks: %d', $updatedBlocks));
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
            $this->info(
                sprintf(
                    'Cannot get transaction [%s] data due to CommandException (%s)(%s)',
                    $transactionId,
                    $commandException->getCode(),
                    $commandException->getMessage()
                )
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
            $adServerAddress = config('app.adshares_address');
            foreach ($transaction->getWires() as $wire) {
                if ($wire->getTargetAddress() === $adServerAddress) {
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
        return $adsPayment->address === config('app.cold_wallet_address');
    }

    private function handleSendOneTx(AdsPayment $adsPayment, SendOneTransaction $transaction): void
    {
        $adsPayment->tx_time = $transaction->getTime();
        $targetAddress = $transaction->getTargetAddress();

        if ($targetAddress === config('app.adshares_address')) {
            $message = $transaction->getMessage();
            $decodedMessage = AdsUtils::decodeMessage($message);

            if (AdsPayment::MESSAGE_JOINING_FEE === $decodedMessage) {
                $this->handleJoiningFee($adsPayment, $transaction);
                return;
            }

            $user = User::fetchByUuid($this->extractUuidFromMessage($message));

            if (null === $user) {
                $this->handleReservedTx($adsPayment);
                return;
            }

            DB::beginTransaction();

            $senderAddress = $transaction->getSenderAddress();
            $appCurrency = Currency::from(config('app.currency'));
            $amount = $transaction->getAmount();
            if (Currency::ADS !== $appCurrency) {
                $amount = $this->exchangeRateReader->fetchExchangeRate(null, $appCurrency->value)
                    ->fromClick($amount);
            }

            $ledgerEntry = UserLedgerEntry::construct(
                $user->id,
                $amount,
                UserLedgerEntry::STATUS_ACCEPTED,
                UserLedgerEntry::TYPE_DEPOSIT
            )->addressed($senderAddress, $targetAddress)
                ->processed($adsPayment->txid);

            $adsPayment->status = AdsPayment::STATUS_USER_DEPOSIT;

            $ledgerEntry->save();
            $adsPayment->save();

            if (null !== $user->email) {
                Mail::to($user)->queue(new DepositProcessed($amount, $appCurrency));
            }

            $reactivatedCount = $this->reactivateSuspendedCampaigns($user);
            if ($reactivatedCount > 0) {
                Log::debug(sprintf('We restarted all suspended campaigns owned by user [%s].', $user->id));
                if (null !== $user->email) {
                    Mail::to($user)->queue(new CampaignResume());
                }
            }

            DB::commit();
            $transactionId = $transaction->getId();
            ServerEvent::dispatch(ServerEventType::UserDepositProcessed, compact('amount', 'transactionId'));
        } else {
            $adsPayment->status = AdsPayment::STATUS_INVALID;
            $adsPayment->save();
        }
    }

    private function extractUuidFromMessage(string $message): string
    {
        return substr($message, -32);
    }

    private function handleJoiningFee(AdsPayment $adsPayment, SendOneTransaction $transaction): void
    {
        $adsPayment->status = AdsPayment::STATUS_JOINING_FEE;
        $adsPayment->save();

        TurnoverEntry::increaseOrInsert(
            $transaction->getTime(),
            TurnoverEntryType::DspJoiningFeeIncome,
            $transaction->getAmount(),
            $transaction->getSenderAddress(),
        );
        JoiningFee::create($transaction->getSenderAddress(), $transaction->getAmount());

        $sspHost = SspHost::fetchByAdsAddress($transaction->getSenderAddress());
        if (null === $sspHost) {
            $sspHost = SspHost::create($transaction->getSenderAddress());
        }
        if (
            config('app.joining_fee_enabled') &&
            !$sspHost->accepted &&
            TurnoverEntry::getJoiningFeeIncome($transaction->getSenderAddress()) >= config('app.joining_fee_value')
        ) {
            $sspHost->accept();
        }
    }

    private function reactivateSuspendedCampaigns(User $user): int
    {
        $appCurrency = Currency::from(config('app.currency'));
        $exchangeRate = match ($appCurrency) {
            Currency::ADS => $this->exchangeRateReader->fetchExchangeRate(),
            default => ExchangeRate::ONE($appCurrency),
        };

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
