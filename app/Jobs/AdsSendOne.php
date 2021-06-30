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
use Adshares\Adserver\Models\UserLedgerException;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AdsSendOne implements ShouldQueue
{
    use Dispatchable;
    use Queueable;
    use SerializesModels;

    private const QUEUE_NAME = 'ads';

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
     * @var UserLedgerEntry user id
     */
    private $userLedger;

    /**
     * Create a new job instance.
     *
     * @param UserLedgerEntry $userLedger
     * @param string $addressTo
     * @param int $amount
     * @param null|string $message
     */
    public function __construct(UserLedgerEntry $userLedger, string $addressTo, int $amount, ?string $message = null)
    {
        $this->userLedger = $userLedger;
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
        if (UserLedgerEntry::STATUS_PENDING !== $this->userLedger->status) {
            return;
        }

        try {
            if ($this->userLedger->user->getWalletBalance() < 0) {
                $this->rejectTransactionDueToNegativeBalance();

                return;
            }
        } catch (UserLedgerException $userLedgerException) {
            $this->rejectTransactionDueToNegativeBalance();

            return;
        }

        $command = new SendOneCommand($this->addressTo, $this->amount, $this->message);

        try {
            $response = $adsClient->runTransaction($command);
        } catch (CommandException $exception) {
            if (in_array($exception->getCode(), self::QUEUE_TRY_AGAIN_EXCEPTION_CODES, true)) {
                $logMessage = sprintf(
                    '[AdsSendOne] Send command to (%s) with amount (%s) failed (message: %s).'
                    . ' Will be tried again later. Exception code (%s)',
                    $this->addressTo,
                    $this->amount,
                    $this->message ?? '',
                    $exception->getCode()
                );
                Log::info($logMessage);

                throw new JobException($logMessage);
            }

            Log::error(sprintf('[AdsSendOne] Send command exception: %s', $exception->getMessage()));

            $logMessage = '[AdsSendOne] Send command to (%s) with amount (%s) failed (message: %s).';

            Log::error(sprintf($logMessage, $this->addressTo, $this->amount, $this->message ?? ''));

            $this->userLedger->status = UserLedgerEntry::STATUS_NET_ERROR;
            $this->userLedger->save();

            return;
        }

        $txid = $response->getTx()->getId();

        if (!$txid || !AdsValidator::isTransactionIdValid($txid)) {
            $this->userLedger->status = UserLedgerEntry::STATUS_SYS_ERROR;
            $this->userLedger->save();

            Log::error(sprintf('[AdsSendOne] Invalid txid: (%s)', $txid));

            return;
        }

        $this->userLedger->status = UserLedgerEntry::STATUS_ACCEPTED;
        $this->userLedger->txid = $txid;

        $this->userLedger->save();
    }

    private function rejectTransactionDueToNegativeBalance(): void
    {
        $this->userLedger->status = UserLedgerEntry::STATUS_REJECTED;
        $this->userLedger->save();

        Log::error(sprintf('[AdsSendOne] User %d has negative balance', $this->userLedger->user_id));
    }

    public function failed(Exception $exception): void
    {
        Log::error(sprintf('[AdsSendOne] Job failed with exception (%s)', $exception->getMessage()));

        $this->userLedger->status = UserLedgerEntry::STATUS_NET_ERROR;
        $this->userLedger->save();
    }
}
