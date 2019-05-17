<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Ads\AdsClient;
use Adshares\Ads\Driver\CommandError;
use Adshares\Ads\Entity\Transaction\SendManyTransaction;
use Adshares\Ads\Entity\Transaction\SendManyTransactionWire;
use Adshares\Ads\Entity\Transaction\SendOneTransaction;
use Adshares\Ads\Exception\CommandException;
use Adshares\Adserver\Console\LineFormatterTrait;
use Adshares\Adserver\Exceptions\MissingInitialConfigurationException;
use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Services\PaymentDetailsProcessor;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Application\Service\Exception\EmptyInventoryException;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\Console\Command\LockableTrait;

class AdsProcessTx extends Command
{
    use LineFormatterTrait;
    use LockableTrait;

    public const EXIT_CODE_SUCCESS = 0;

    public const EXIT_CODE_CANNOT_GET_BLOCK_IDS = 1;

    public const EXIT_CODE_COMMAND_LOCKED = 2;

    protected $signature = 'ads:process-tx';

    protected $description = 'Processes incoming txs';

    private $adServerAddress;

    /** @var ExchangeRateReader */
    private $exchangeRateReader;

    /** @var DemandClient $demandClient */
    private $demandClient;

    /** @var PaymentDetailsProcessor $paymentDetailsProcessor */
    private $paymentDetailsProcessor;

    public function __construct(ExchangeRateReader $exchangeRateReader)
    {
        parent::__construct();
        $this->adServerAddress = config('app.adshares_address');
        $this->exchangeRateReader = $exchangeRateReader;
    }

    public function handle(
        AdsClient $adsClient,
        PaymentDetailsProcessor $paymentDetailsProcessor,
        DemandClient $demandClient
    ): int {
        if (!$this->lock()) {
            $this->info('[AdsProcessTx] Command '.$this->signature.' already running.');

            return self::EXIT_CODE_COMMAND_LOCKED;
        }

        $this->info('Start command '.$this->signature);
        $this->demandClient = $demandClient;
        $this->paymentDetailsProcessor = $paymentDetailsProcessor;

        try {
            $this->updateBlockIds($adsClient);
        } catch (CommandException $exc) {
            $code = $exc->getCode();
            $message = $exc->getMessage();
            $this->error(
                "Cannot update blocks due to CommandException:\n"."Code:\n  ${code}\n"."Message:\n  ${message}\n"
            );

            $this->info('Premature finish processing incoming txs.');

            return self::EXIT_CODE_CANNOT_GET_BLOCK_IDS;
        }

        $dbTxs = AdsPayment::where('status', AdsPayment::STATUS_NEW)->get();

        foreach ($dbTxs as $dbTx) {
            $this->handleDbTx($adsClient, $dbTx);
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

    private function handleDbTx(AdsClient $adsClient, $dbTx): void
    {
        try {
            $txid = $dbTx->txid;
            $transaction = $adsClient->getTransaction($txid)->getTxn();
        } catch (CommandException $exc) {
            $code = $exc->getCode();
            $message = $exc->getMessage();
            $this->info(
                "Cannot get transaction [$txid] data due to CommandException:\nCode:\n  ${code}\n"
                ."Message:\n  ${message}\n"
            );

            return;
        }

        $type = $transaction->getType();
        switch ($type) {
            case 'send_many':
                /** @var $transaction SendManyTransaction */
                $this->handleSendManyTx($dbTx, $transaction);
                break;

            case 'send_one':
                /** @var $transaction SendOneTransaction */
                $this->handleSendOneTx($dbTx, $transaction);
                break;

            default:
                $dbTx->status = AdsPayment::STATUS_INVALID;
                $dbTx->save();
                break;
        }
    }

    private function handleSendManyTx(AdsPayment $dbTx, SendManyTransaction $transaction): void
    {
        $isTxTargetValid = false;
        $wiresCount = $transaction->getWireCount();

        if ($wiresCount > 0) {
            $wires = $transaction->getWires();

            foreach ($wires as $wire) {
                /** @var $wire SendManyTransactionWire */
                $targetAddr = $wire->getTargetAddress();
                if ($targetAddr === $this->adServerAddress) {
                    $isTxTargetValid = true;
                    break;
                }
            }
        }

        if ($isTxTargetValid) {
            $this->handleReservedTx($dbTx);
        } else {
            $dbTx->status = AdsPayment::STATUS_INVALID;
            $dbTx->save();
        }
    }

    private function handleReservedTx(AdsPayment $dbTx): void
    {
        if ($this->checkIfColdWalletTransaction($dbTx)) {
            $dbTx->status = AdsPayment::STATUS_TRANSFER_FROM_COLD_WALLET;
            $dbTx->save();
        } elseif (!$this->handleIfEventPayment($dbTx)) {
            $dbTx->status = AdsPayment::STATUS_RESERVED;
            $dbTx->save();
        }
    }

    private function checkIfColdWalletTransaction(AdsPayment $dbTx): bool
    {
        $coldWalletAddress = config('app.adshares_wallet_cold_address');

        if ($dbTx->address === $coldWalletAddress) {
            return true;
        }

        return false;
    }

    private function handleIfEventPayment(AdsPayment $dbTx): bool
    {
        $senderAddress = $dbTx->address;
        $networkHost = NetworkHost::fetchByAddress($senderAddress);

        if ($networkHost === null) {
            return false;
        }

        $host = $networkHost->host;
        $txid = $dbTx->txid;
        $adsPaymentId = $dbTx->id;

        try {
            $paymentDetails = $this->demandClient->fetchPaymentDetails($host, $txid);
        } catch (EmptyInventoryException $exception) {
            return false;
        } catch (UnexpectedClientResponseException $exception) {
            if ($exception->getCode() === Response::HTTP_NOT_FOUND) {
                return false;
            }

            return true;
        }

        try {
            $this->paymentDetailsProcessor->processPaymentDetails($senderAddress, $adsPaymentId, $paymentDetails);
        } catch (MissingInitialConfigurationException $exception) {
            $this->error('Missing initial configuration: '.$exception->getMessage());

            return true;
        } catch (Exception $exception) {
            $this->error('Unexpected error: '.$exception->getMessage());

            return true;
        }

        $dbTx->status = AdsPayment::STATUS_EVENT_PAYMENT;
        $dbTx->save();

        return true;
    }

    private function handleSendOneTx(AdsPayment $dbTx, SendOneTransaction $transaction): void
    {
        $targetAddr = $transaction->getTargetAddress();

        if ($targetAddr === $this->adServerAddress) {
            $message = $transaction->getMessage();
            $uuid = $this->extractUuidFromMessage($message);
            $user = User::fetchByUuid($uuid);

            if (null === $user) {
                $this->handleReservedTx($dbTx);
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
                    ->processed($dbTx->txid);

                $dbTx->status = AdsPayment::STATUS_USER_DEPOSIT;

                $ledgerEntry->save();
                $dbTx->save();

                try {
                    $this->reactivateSuspendedCampaigns($user);
                    Log::debug("Notify user [{$user->id}] that we restarted all suspended campaigns.");
                } catch (InvalidArgumentException $exception) {
                    Log::debug("Notify user [{$user->id}] that we cannot restart campaigns.");
                }

                DB::commit();
            }
        } else {
            $dbTx->status = AdsPayment::STATUS_INVALID;
            $dbTx->save();
        }
    }

    private function extractUuidFromMessage(string $message): string
    {
        return substr($message, -32);
    }

    private function reactivateSuspendedCampaigns(User $user): void
    {
        $exchangeRate = $this->exchangeRateReader->fetchExchangeRate();

        $balance = UserLedgerEntry::getBalanceByUserId($user->id);
        $campaigns = $user->campaigns;

        $requiredBudget = $campaigns->filter(function (Campaign $campaign) {
            return $campaign->status === Campaign::STATUS_ACTIVE || $campaign->status === Campaign::STATUS_SUSPENDED;
        })->sum('budget');

        $requiredBalance = $exchangeRate->toClick($requiredBudget);

        if ($balance >= $requiredBalance) {
            $campaigns->filter(function (Campaign $campaign) {
                return $campaign->status === Campaign::STATUS_SUSPENDED;
            })->each(function (Campaign $campaign) use ($exchangeRate) {
                $campaign->changeStatus(Campaign::STATUS_ACTIVE, $exchangeRate);
                $campaign->save();
            });
        }
    }
}
