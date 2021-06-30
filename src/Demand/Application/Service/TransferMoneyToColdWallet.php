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

declare(strict_types=1);

namespace Adshares\Demand\Application\Service;

use Adshares\Ads\AdsClient;
use Adshares\Ads\Command\SendOneCommand;
use Adshares\Ads\Util\AdsValidator;
use Adshares\Demand\Application\Dto\TransferMoneyResponse;
use Adshares\Demand\Application\Exception\TransferMoneyException;

class TransferMoneyToColdWallet
{
    /** @var int */
    private $minAmount;

    /** @var int */
    private $maxAmount;

    /** @var string */
    private $coldWalletAddress;

    /** @var AdsClient */
    private $adsClient;

    public function __construct(int $minAmount, int $maxAmount, string $coldWalletAddress, AdsClient $adsClient)
    {
        $this->minAmount = $minAmount;
        $this->maxAmount = $maxAmount;
        $this->coldWalletAddress = $coldWalletAddress;
        $this->adsClient = $adsClient;
    }

    public function transfer(int $waitingPaymentsAmount): ?TransferMoneyResponse
    {
        $waitingPaymentsAmount = (int)abs($waitingPaymentsAmount);
        $limit = $this->calculateLimitValue();
        $operatorBalance = $this->fetchOperatorBalance();

        if ($operatorBalance - $waitingPaymentsAmount > $this->maxAmount) {
            $transferValue = $operatorBalance - $waitingPaymentsAmount - $limit;
            $transactionId = $this->sendOneCommand($transferValue);

            if (!AdsValidator::isTransactionIdValid($transactionId)) {
                $message = sprintf(
                    '[Wallet] There were some problems with transfer %s clicks to Cold Wallet (%s) (txid: %s).',
                    $transferValue,
                    $this->coldWalletAddress,
                    $transactionId
                );

                throw new TransferMoneyException($message);
            }

            return new TransferMoneyResponse($transferValue, $transactionId);
        }

        return null;
    }

    private function calculateLimitValue(): int
    {
        return (int)floor(($this->minAmount + $this->maxAmount) / 2);
    }

    private function fetchOperatorBalance(): int
    {
        $me = $this->adsClient->getMe();
        return $me->getAccount()->getBalance();
    }

    private function sendOneCommand($transferValue): string
    {
        $command = new SendOneCommand($this->coldWalletAddress, $transferValue);
        $response = $this->adsClient->runTransaction($command);

        return $response->getTx()->getId();
    }
}
