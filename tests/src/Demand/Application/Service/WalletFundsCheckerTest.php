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

namespace Adshares\Tests\Demand\Application\Service;

use Adshares\Ads\AdsClient;
use Adshares\Ads\Entity\Account;
use Adshares\Ads\Response\GetAccountResponse;
use Adshares\Demand\Application\Service\WalletFundsChecker;
use PHPUnit\Framework\TestCase;

class WalletFundsCheckerTest extends TestCase
{
    public function testTransferWhenHotWalletIncludingPaymentsWaitingIsLowerThanMin(): void
    {
        $min = 20;
        $max = 100;
        // limit = ($max + $min) / 2 = 60
        $hotWalletValue = 5;
        $waitingPayments = 8;
        $allUsersBalance = 20;

        $service = new WalletFundsChecker(
            $min,
            $max,
            $this->createAdsClientMock($hotWalletValue)
        );

        $transferValue = $service->calculateTransferValue($waitingPayments, $allUsersBalance);

        $this->assertEquals(60 + 8 - 5, $transferValue);
    }

    public function testTransferWhenHotWalletIncludingPaymentsWaitingIsGreaterThanMin(): void
    {
        $min = 20;
        $max = 100;
        $hotWalletValue = 20;
        $waitingPayments = 15;
        $allUsersBalance = 20;

        $service = new WalletFundsChecker(
            $min,
            $max,
            $this->createAdsClientMock($hotWalletValue)
        );

        $transferValue = $service->calculateTransferValue($waitingPayments, $allUsersBalance);

        $this->assertEquals(55, $transferValue);
    }

    public function testTransferWhenOperatorBalanceIsLowerThanUsersBalance(): void
    {
        $min = 20;
        $max = 100;
        $hotWalletValue = 5;
        $waitingPayments = 15;
        $allUsersBalance = 10;

        $service = new WalletFundsChecker(
            $min,
            $max,
            $this->createAdsClientMock($hotWalletValue)
        );

        $transferValue = $service->calculateTransferValue($waitingPayments, $allUsersBalance);

        $this->assertEquals(70, $transferValue);
    }

    public function testTransferWhenOperatorBalanceIsGreaterThanUsersBalance(): void
    {
        $min = 20;
        $max = 100;
        $hotWalletValue = 10;
        $waitingPayments = 0;
        $allUsersBalance = 5;

        $service = new WalletFundsChecker(
            $min,
            $max,
            $this->createAdsClientMock($hotWalletValue)
        );

        $transferValue = $service->calculateTransferValue($waitingPayments, $allUsersBalance);

        $this->assertEquals(0, $transferValue);
    }

    private function createAdsClientMock(int $hotWalletValue)
    {
        $adsClient = $this->createMock(AdsClient::class);
        $account = $this->createMock(Account::class);
        $account
            ->expects($this->once())
            ->method('getBalance')
            ->willReturn($hotWalletValue);

        $accountResponse = $this->createMock(GetAccountResponse::class);
        $accountResponse
            ->method('getAccount')
            ->willReturn($account);

        $adsClient
            ->expects($this->once())
            ->method('getMe')
            ->willReturn($accountResponse)
        ;

        return $adsClient;
    }
}
