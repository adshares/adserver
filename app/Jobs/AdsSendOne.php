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
use Adshares\Ads\Exception\CommandException;
use Adshares\Ads\Util\AdsValidator;
use Adshares\Adserver\Exceptions\JobException;
use Adshares\Adserver\Models\UserLedger;
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
    /**
     * @var string recipent address
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
     * @var UserLedger user id
     */
    private $userLedger;

    /**
     * Create a new job instance.
     *
     * @param UserLedger $userLedger
     * @param string $addressTo
     * @param int $amount
     * @param null|string $message
     */
    public function __construct(UserLedger $userLedger, string $addressTo, int $amount, ?string $message)
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
     * @throws CommandException
     * @throws JobException
     */
    public function handle(AdsClient $adsClient)
    {
        $total = -$this->userLedger->amount;
        $balance = UserLedger::getBalanceByUserId($this->userLedger->user_id);

        if ($balance < $total) {
            Log::notice("Insufficient funds.");
            $this->userLedger->status = UserLedger::STATUS_REJECTED;
            $this->userLedger->save();

            return;
        }

        $command = new SendOneCommand($this->addressTo, $this->amount, $this->message);
        // runTransaction throws CommandException, which can be treated as job fail
        $response = $adsClient->runTransaction($command);

        $txid = $response->getTx()->getId();

        if (AdsValidator::isTransactionIdValid($txid)) {
            // TODO move to queue (or service in general) to assure user ledger entry update
            $this->userLedger->status = UserLedger::STATUS_ACCEPTED;
            $this->userLedger->txid = $txid;
            $this->userLedger->save();
        } else {
            throw new JobException("Invalid txid: ${txid}");
        }
    }
}
