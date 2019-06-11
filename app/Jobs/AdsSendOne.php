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

namespace Adshares\Adserver\Jobs;

use Adshares\Ads\AdsClient;
use Adshares\Ads\Command\SendOneCommand;
use Adshares\Ads\Driver\CommandError;
use Adshares\Ads\Exception\CommandException;
use Adshares\Ads\Util\AdsValidator;
use Adshares\Adserver\Exceptions\JobException;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Models\UserLedgerException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AdsSendOne implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const QUEUE_NAME = 'ads';

    private const QUEUE_TRY_AGAIN_INTERVAL = 600;

    private const QUEUE_TRY_AGAIN_EXCEPTION_CODES = [
        CommandError::LOW_BALANCE,
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
    public function __construct(UserLedgerEntry $userLedger, string $addressTo, int $amount, ?string $message)
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
     * @throws UserLedgerException
     * @throws JobException
     */
    public function handle(AdsClient $adsClient): void
    {
        if (UserLedgerEntry::STATUS_PENDING !== $this->userLedger->status) {
            return;
        }

        if (UserLedgerEntry::getWalletBalanceByUserId($this->userLedger->user_id) < 0) {
            $this->userLedger->status = UserLedgerEntry::STATUS_REJECTED;
            $this->userLedger->save();

            return;
        }

        $command = new SendOneCommand($this->addressTo, $this->amount, $this->message);

        try {
            $response = $adsClient->runTransaction($command);
        } catch (CommandException $exception) {
            if (in_array($exception->getCode(), self::QUEUE_TRY_AGAIN_EXCEPTION_CODES, true)) {
                $message = '[ADS] Send command to (%s) with amount (%s) failed (message: %s).';
                $message .= ' Will be tried again later. Exception code (%s)';
                Log::info(
                    sprintf($message, $this->addressTo, $this->amount, $this->message ?? '', $exception->getCode())
                );

                self::dispatch($this->userLedger, $this->addressTo, $this->amount, $this->message)
                    ->delay(self::QUEUE_TRY_AGAIN_INTERVAL);

                return;
            }

            Log::error(sprintf('[ADS] Send command exception: %s', $exception->getMessage()));

            $message = '[ADS] Send command to (%s) with amount (%s) failed (message: %s).';

            Log::error(sprintf($message, $this->addressTo, $this->amount, $this->message ?? ''));

            $this->userLedger->status = UserLedgerEntry::STATUS_NET_ERROR;

            $this->userLedger->save();

            return;
        }

        $txid = $response->getTx()->getId();

        if (!$txid || !AdsValidator::isTransactionIdValid($txid)) {
            $this->userLedger->status = UserLedgerEntry::STATUS_SYS_ERROR;
            $this->userLedger->save();

            throw new JobException("Invalid txid: ${txid}");
        }

        $this->userLedger->status = UserLedgerEntry::STATUS_ACCEPTED;
        $this->userLedger->txid = $txid;

        $this->userLedger->save();
    }
}
