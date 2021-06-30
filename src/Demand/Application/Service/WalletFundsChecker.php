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
use Adshares\Adserver\Models\UserLedgerEntry;

class WalletFundsChecker
{
    /** @var int */
    private $minAmount;

    /** @var int */
    private $maxAmount;

    /** @var AdsClient */
    private $adsClient;

    public function __construct(int $minAmount, int $maxAmount, AdsClient $adsClient)
    {
        $this->minAmount = $minAmount;
        $this->maxAmount = $maxAmount;
        $this->adsClient = $adsClient;
    }

    public function calculateTransferValue(int $waitingPaymentsAmount, int $allUsersBalance): int
    {
        $waitingPaymentsAmount = (int)abs($waitingPaymentsAmount);
        $limit = $this->calculateLimitValue();
        $adsOperatorBalance = $this->fetchOperatorBalance();
        $actualOperatorBalance = $adsOperatorBalance - $waitingPaymentsAmount;

        if ($actualOperatorBalance < min($this->minAmount, $allUsersBalance)) {
            return $limit - $actualOperatorBalance;
        }

        return 0;
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
}
