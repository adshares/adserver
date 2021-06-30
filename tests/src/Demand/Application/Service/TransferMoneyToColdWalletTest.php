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
use Adshares\Ads\Entity\Tx;
use Adshares\Ads\Response\GetAccountResponse;
use Adshares\Ads\Response\TransactionResponse;
use Adshares\Demand\Application\Service\TransferMoneyToColdWallet;
use PHPUnit\Framework\TestCase;

final class TransferMoneyToColdWalletTest extends TestCase
{
    public function testTransferWhenHotWalletIncludingPaymentsWaitingIsBiggerThanMax(): void
    {
        $min = 10;
        $max = 100;
        // limit = ($max + $min) / 2 = 55
        $hotWalletValue = 150;
        $coldWalletAddress = '0003-00000002-1234';
        $waitingPayments = 20;
        $transactionId = '0003:00000001:1234';

        $service = new TransferMoneyToColdWallet(
            $min,
            $max,
            $coldWalletAddress,
            $this->createAdsClientMock($hotWalletValue, $transactionId)
        );

        $response = $service->transfer($waitingPayments);

        $this->assertEquals(75, $response->getTransferValue());
        $this->assertEquals($transactionId, $response->getTransactionId());
    }

    public function testIfTransferIsNotMakingWhenHotWalletIncludingPaymentsWaitingIsLowerThanMax(): void
    {
        $min = 10;
        $max = 100;
        // limit = ($max + $min) / 2 = 55
        $hotWalletValue = 150;
        $coldWalletAddress = '0003-00000002-1234';
        $waitingPayments = 100;

        $service = new TransferMoneyToColdWallet(
            $min,
            $max,
            $coldWalletAddress,
            $this->createAdsClientMock($hotWalletValue)
        );

        $response = $service->transfer($waitingPayments);

        $this->assertNull($response);
    }

    public function testIfTransferIsNotMakingWhenHotWalletIsLowerThanMax(): void
    {
        $min = 10;
        $max = 100;
        $hotWalletValue = 90;
        $coldWalletAddress = '0003-00000002-1234';
        $waitingPayments = 0;

        $service = new TransferMoneyToColdWallet(
            $min,
            $max,
            $coldWalletAddress,
            $this->createAdsClientMock($hotWalletValue)
        );

        $response = $service->transfer($waitingPayments);

        $this->assertNull($response);
    }

    private function createAdsClientMock(int $hotWalletValue, ?string $transactionId = null)
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

        if ($transactionId) {
            $transaction = $this->createMock(Tx::class);
            $transaction
                ->expects($this->once())
                ->method('getId')
                ->willReturn($transactionId);

            $transactionResponse = $this->createMock(TransactionResponse::class);
            $transactionResponse
                ->expects($this->once())
                ->method('getTx')
                ->willReturn($transaction);

            $adsClient
                ->expects($this->once())
                ->method('runTransaction')
                ->willReturn($transactionResponse)
            ;
        } else {
            $adsClient
                ->expects($this->never())
                ->method('runTransaction')
            ;
        }

        return $adsClient;
    }
}
