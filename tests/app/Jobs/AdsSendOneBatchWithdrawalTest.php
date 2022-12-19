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

namespace Adshares\Adserver\Tests\Jobs;

use Adshares\Ads\AdsClient;
use Adshares\Adserver\Jobs\AdsSendOneBatchWithdrawal;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Ads\Response\TransactionResponse;
use Adshares\Ads\Command\SendOneCommand;
use Adshares\Ads\Exception\CommandException;
use Adshares\Ads\Entity\Tx;

use function factory;

class AdsSendOneBatchWithdrawalTest extends TestCase
{


    private function createBatchEntries(string $batchId): void
    {
        $entries = [
            100,
            40,
            1,
            11,
        ];    
        foreach ($entries as $entry) {
            $user = User::factory()->create();
            UserLedgerEntry::constructForeignEntry(
                $batchId,
                $user->id,
                $entry
            )->save();
        }
    }

    public function testWorking(): void
    {
        $mockAdsClient = $this->createMock(AdsClient::class);
        $mockResponse = $this->createMock(TransactionResponse::class);
        $mockTx = $this->createMock(Tx::class);

        $txid = '0004:000044B3:0001';
        $mockTx->method('getId')->willReturn($txid);
        $mockResponse->method('getTx')->willReturn($mockTx);
        $mockAdsClient->method('runTransaction')->willReturn($mockResponse);


        $batchId = UserLedgerEntry::getNewBatchId();
        $this->createBatchEntries($batchId);
        $amount = 152;
        $addressTo = '0001-00000000-9B6F';

        $job = new AdsSendOneBatchWithdrawal($batchId, $addressTo, $amount);
        /** @var AdsClient $mockAdsClient */
        $job->handle($mockAdsClient);

        $userLedgerEntries = UserLedgerEntry::all();
        $this->assertCount(4, $userLedgerEntries);
        $this->assertEquals(UserLedgerEntry::STATUS_ACCEPTED, $userLedgerEntries->get(0)->status);
    }

    public function testError(): void
    {
        $mockAdsClient = $this->createMock(AdsClient::class);
        $mockResponse = $this->createMock(TransactionResponse::class);
        $mockTx = $this->createMock(Tx::class);
        $mockAdsClient->method('runTransaction')->will($this->throwException(new CommandException(new SendOneCommand('11',12))));


        $batchId = UserLedgerEntry::getNewBatchId();
        $this->createBatchEntries($batchId);
        $amount = 152;
        $addressTo = '0001-00000000-9B6F';

        $job = new AdsSendOneBatchWithdrawal($batchId, $addressTo, $amount);
        /** @var AdsClient $mockAdsClient */
        $job->handle($mockAdsClient);

        $userLedgerEntries = UserLedgerEntry::all();
        $this->assertCount(4, $userLedgerEntries);
        $this->assertEquals(UserLedgerEntry::STATUS_NET_ERROR, $userLedgerEntries->get(0)->status);
    }
}