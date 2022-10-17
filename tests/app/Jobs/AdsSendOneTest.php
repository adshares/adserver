<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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
use Adshares\Ads\Command\SendOneCommand;
use Adshares\Ads\Driver\CommandError;
use Adshares\Ads\Entity\Tx;
use Adshares\Ads\Exception\CommandException;
use Adshares\Ads\Response\TransactionResponse;
use Adshares\Adserver\Events\ServerEvent;
use Adshares\Adserver\Exceptions\JobException;
use Adshares\Adserver\Jobs\AdsSendOne;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\ViewModel\ServerEventType;
use Adshares\Common\Exception\RuntimeException;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\MockObject\MockObject;

class AdsSendOneTest extends TestCase
{
    public function testSend(): void
    {
        $userLedgerEntry = $this->getUserLedgerEntry();
        $adsClient = $this->getAdsClientMock();

        $job = new AdsSendOne($userLedgerEntry, $userLedgerEntry->address_to, -$userLedgerEntry->amount);
        $job->handle($adsClient);

        self::assertDatabaseHas(UserLedgerEntry::class, [
            'id' => $userLedgerEntry->id,
            'status' => UserLedgerEntry::STATUS_ACCEPTED,
        ]);
        self::assertServerEventDispatched(ServerEventType::UserWithdrawalProcessed);
    }

    public function testCanceledWithdrawal(): void
    {
        $userLedgerEntry = $this->getUserLedgerEntry();
        $userLedgerEntry->status = UserLedgerEntry::STATUS_CANCELED;
        $userLedgerEntry->save();
        $adsClient = $this->createMock(AdsClient::class);

        $job = new AdsSendOne($userLedgerEntry, $userLedgerEntry->address_to, -$userLedgerEntry->amount);
        $job->handle($adsClient);

        Event::assertNotDispatched(ServerEvent::class);
    }

    public function testNegativeBalance(): void
    {
        $adsClient = $this->createMock(AdsClient::class);
        /** @var User $user */
        $user = User::factory()->create();
        $userLedgerEntry = UserLedgerEntry::construct(
            $user->id,
            -10000,
            UserLedgerEntry::STATUS_PENDING,
            UserLedgerEntry::TYPE_WITHDRAWAL
        )->addressed(
            '0001-00000000-9B6F',
            '0001-00000000-9B6F'
        );
        $userLedgerEntry->save();

        $job = new AdsSendOne($userLedgerEntry, $userLedgerEntry->address_to, -$userLedgerEntry->amount);
        /** @var AdsClient $adsClient */
        $job->handle($adsClient);

        self::assertDatabaseHas(UserLedgerEntry::class, [
            'id' => $userLedgerEntry->id,
            'status' => UserLedgerEntry::STATUS_REJECTED,
        ]);
        Event::assertNotDispatched(ServerEvent::class);
    }

    public function testInvalidTransactionId(): void
    {
        $userLedgerEntry = $this->getUserLedgerEntry();
        $adsClient = $this->createMock(AdsClient::class);

        $job = new AdsSendOne($userLedgerEntry, $userLedgerEntry->address_to, -$userLedgerEntry->amount);
        $job->handle($adsClient);

        self::assertDatabaseHas(UserLedgerEntry::class, [
            'id' => $userLedgerEntry->id,
            'status' => UserLedgerEntry::STATUS_SYS_ERROR,
        ]);
        Event::assertNotDispatched(ServerEvent::class);
    }

    public function testWhileRunTransactionRecoverableError(): void
    {
        $userLedgerEntry = $this->getUserLedgerEntry();
        $adsClient = $this->createMock(AdsClient::class);
        $adsClient->expects(self::once())->method('runTransaction')
            ->willThrowException(
                new CommandException(
                    new SendOneCommand($userLedgerEntry->address_to, -$userLedgerEntry->amount),
                    '',
                    CommandError::LOCK_USER_FAILED,
                )
            );

        $job = new AdsSendOne($userLedgerEntry, $userLedgerEntry->address_to, -$userLedgerEntry->amount);
        self::expectException(JobException::class);
        $job->handle($adsClient);
    }

    public function testWhileRunTransactionUnrecoverableError(): void
    {
        $userLedgerEntry = $this->getUserLedgerEntry();
        $adsClient = $this->createMock(AdsClient::class);
        $adsClient->expects(self::once())->method('runTransaction')
            ->willThrowException(
                new CommandException(
                    new SendOneCommand($userLedgerEntry->address_to, -$userLedgerEntry->amount)
                )
            );

        $job = new AdsSendOne($userLedgerEntry, $userLedgerEntry->address_to, -$userLedgerEntry->amount);
        $job->handle($adsClient);

        self::assertDatabaseHas(UserLedgerEntry::class, [
            'id' => $userLedgerEntry->id,
            'status' => UserLedgerEntry::STATUS_NET_ERROR,
        ]);
        Event::assertNotDispatched(ServerEvent::class);
    }

    public function testFailed(): void
    {
        /** @var UserLedgerEntry $userLedgerEntry */
        $userLedgerEntry = UserLedgerEntry::factory()->create(['status' => UserLedgerEntry::STATUS_PENDING]);

        $job = new AdsSendOne($userLedgerEntry, $userLedgerEntry->address_to, -$userLedgerEntry->amount);
        $job->failed(new RuntimeException('test-exception'));

        self::assertDatabaseHas(UserLedgerEntry::class, [
            'id' => $userLedgerEntry->id,
            'status' => UserLedgerEntry::STATUS_NET_ERROR,
        ]);
    }

    public function testGetAmount(): void
    {
        $amount = 1.2e11;
        /** @var UserLedgerEntry $userLedgerEntry */
        $userLedgerEntry = UserLedgerEntry::factory()->create(['amount' => $amount]);

        $job = new AdsSendOne($userLedgerEntry, $userLedgerEntry->address_to, -$userLedgerEntry->amount);

        self::assertEquals($amount, $job->getAmount());
    }

    private function getAdsClientMock(): MockObject|AdsClient
    {
        $transaction = $this->createMock(Tx::class);
        $transaction
            ->expects(self::once())
            ->method('getId')
            ->willReturn('0001:00000009:0001');

        $transactionResponse = $this->createMock(TransactionResponse::class);
        $transactionResponse
            ->expects(self::once())
            ->method('getTx')
            ->willReturn($transaction);

        $adsClient = $this->createMock(AdsClient::class);
        $adsClient
            ->expects(self::once())
            ->method('runTransaction')
            ->willReturn($transactionResponse);
        return $adsClient;
    }

    private function getUserLedgerEntry(): UserLedgerEntry
    {
        /** @var User $user */
        $user = User::factory()->create();
        UserLedgerEntry::factory()->create([
            'amount' => 2e11,
            'status' => UserLedgerEntry::STATUS_ACCEPTED,
            'type' => UserLedgerEntry::TYPE_DEPOSIT,
            'user_id' => $user->id,
        ]);
        /** @var UserLedgerEntry $userLedgerEntry */
        $userLedgerEntry = UserLedgerEntry::factory()->create([
            'amount' => -1.2e11,
            'status' => UserLedgerEntry::STATUS_PENDING,
            'txid' => null,
            'type' => UserLedgerEntry::TYPE_WITHDRAWAL,
            'user_id' => $user->id,
        ]);
        return $userLedgerEntry;
    }
}
