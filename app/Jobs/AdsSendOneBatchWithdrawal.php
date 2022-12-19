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

namespace Adshares\Adserver\Jobs;

use Adshares\Ads\AdsClient;
use Adshares\Ads\Command\SendOneCommand;
use Adshares\Ads\Driver\CommandError;
use Adshares\Ads\Exception\CommandException;
use Adshares\Ads\Util\AdsValidator;
use Adshares\Adserver\Exceptions\JobException;
use Adshares\Adserver\Models\UserLedgerEntry;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AdsSendOneBatchWithdrawal implements ShouldQueue
{
    use Dispatchable;
    use Queueable;
    use SerializesModels;

    private const QUEUE_NAME = 'ads';
    public $tries = 5;

    private const QUEUE_TRY_AGAIN_EXCEPTION_CODES = [
        CommandError::LOW_BALANCE,
        CommandError::USER_BAD_TARGET,
        CommandError::LOCK_USER_FAILED,
    ];

    /**
     * @var string recipient address
     */
    private $addressTo;

    /**
     * @var int transfer amount
     */
    private $amount;

    /**
     * @var null|string optional message
     */
    private $message;

    /**
     * @var string batchId
     */
    private $batchId;

    /**
     * Create a new job instance.
     *
     * @param string $batchId
     * @param string $addressTo
     * @param int $amount
     * @param null|string $message
     */
    public function __construct(string $batchId, string $addressTo, int $amount, ?string $message = null)
    {
        $this->batchId = $batchId;
        $this->addressTo = $addressTo;
        $this->amount = $amount;
        $this->message = $message;
        $this->queue = self::QUEUE_NAME;
    }

    /**
     * Execute the job.
     *
     * @param AdsClient $adsClient
     *
     * @throws JobException
     */
    public function handle(AdsClient $adsClient): void
    {
        $userLedger = UserLedgerEntry::getFirstRecordByBatchId($this->batchId);
        if (!$userLedger){
            return;
        }
        if (UserLedgerEntry::STATUS_PENDING !== $userLedger->status) {
            return;
        }

        $command = new SendOneCommand($this->addressTo, $this->amount, $this->message);

        try {
            $response = $adsClient->runTransaction($command);
        } catch (CommandException $exception) {
            if (in_array($exception->getCode(), self::QUEUE_TRY_AGAIN_EXCEPTION_CODES, true)) {
                $logMessage = sprintf(
                    '[AdsSendOneBatchWithdrawal] Send command to (%s) with amount (%s) failed (message: %s).'
                    . ' Will be tried again later. Exception code (%s)',
                    $this->addressTo,
                    $this->amount,
                    $this->message ?? '',
                    $exception->getCode()
                );
                Log::info($logMessage);

                throw new JobException($logMessage);
            }

            Log::error(sprintf('[AdsSendOneBatchWithdrawal] Send command exception: %s', $exception->getMessage()));

            $logMessage = '[AdsSendOneBatchWithdrawal] Send command to (%s) with amount (%s) failed (message: %s).';

            Log::error(sprintf($logMessage, $this->addressTo, $this->amount, $this->message ?? ''));

            UserLedgerEntry::failAllRecordsInBatch($this->batchId, UserLedgerEntry::STATUS_NET_ERROR);

            return;
        }

        $txid = $response->getTx()->getId();

        if ($this->isTxValid($txid)) {
            UserLedgerEntry::failAllRecordsInBatch($this->batchId, UserLedgerEntry::STATUS_SYS_ERROR);

            Log::error(sprintf('[AdsSendOneBatchWithdrawal] Invalid txid: (%s)', $txid));

            return;
        }
        UserLedgerEntry::acceptAllRecordsInBatch($this->batchId, $txid);
    }

    private function isTxValid(string $txid){
        return !$txid || !AdsValidator::isTransactionIdValid($txid);
    }

    public function failed(Exception $exception): void
    {
        Log::error(sprintf('[AdsSendOneBatchWithdrawal] Job failed with exception (%s)', $exception->getMessage()));
        UserLedgerEntry::failAllRecordsInBatch($this->batchId, UserLedgerEntry::STATUS_NET_ERROR);
    }
}