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

declare(strict_types=1);

namespace Adshares\Adserver\Console\Commands;

use Adshares\Ads\AdsClient;
use Adshares\Ads\Command\SendOneCommand;
use Adshares\Ads\Exception\CommandException;
use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\TurnoverEntry;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Supply\Domain\ValueObject\TurnoverEntryType;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;

class SendJoiningFee extends BaseCommand
{
    private const COMMAND_SIGNATURE = 'ops:supply:joining-fee';

    protected $signature = self::COMMAND_SIGNATURE
    . ' {address : Recipient address}'
    . ' {amount : Amount to send in clicks}';
    protected $description = 'Sends amount to DSP to cover joining fee';

    public function __construct(
        Locker $locker,
        private readonly AdsClient $adsClient,
    ) {
        parent::__construct($locker);
    }

    public function handle(): int
    {
        $this->info(sprintf('Start command %s', self::COMMAND_SIGNATURE));

        $address = $this->argument('address');
        $amount = (int)$this->argument('amount');

        if (!AccountId::isValid($address)) {
            $this->error('Invalid address');
            return self::FAILURE;
        }

        $this->info(sprintf('Sending %d to %s', $amount, $address));
        $command = new SendOneCommand($address, $amount, AdsUtils::encodeMessage(AdsPayment::MESSAGE_JOINING_FEE));

        try {
            $response = $this->adsClient->runTransaction($command);
        } catch (CommandException $exception) {
            Log::error(sprintf('Sending failed. SendOneCommand exception: %s', $exception->getMessage()));
            $this->error('Sending failed');
            return self::FAILURE;
        }

        $transactionId = $response->getTx()->getId();
        if (null === $transactionId) {
            Log::error('Transaction ID is null');
            $this->error('Transaction ID is null');
            return self::FAILURE;
        }

        TurnoverEntry::increaseOrInsert(
            new DateTimeImmutable(),
            TurnoverEntryType::SspJoiningFeeExpense,
            $amount,
            $address,
        );

        $this->info(sprintf('Transaction sent. ID is %s', $transactionId));
        $this->info(sprintf('Finish command %s', self::COMMAND_SIGNATURE));

        return self::SUCCESS;
    }
}
