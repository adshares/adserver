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

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Ads\Response\GetTransactionResponse;
use Adshares\Adserver\Console\Commands\DemandSendPayments;
use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\Payment;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Common\Application\Service\Ads;
use Adshares\Common\Application\Service\Exception\AdsException;
use Illuminate\Database\Eloquent\Collection;

use function factory;

class DemandSendPaymentsTest extends ConsoleTestCase
{
    public function testNoPayments(): void
    {
        $this->artisan('ops:demand:payments:send')
            ->expectsOutput('Found 0 sendable payments.')
            ->assertExitCode(0);
    }

    public function testZeroPayment(): void
    {
        factory(Payment::class)->create(['state' => Payment::STATE_NEW, 'fee' => 0]);

        $this->artisan('ops:demand:payments:send')
            ->expectsOutput('Found 0 sendable payments.')
            ->assertExitCode(0);

        $payments = Payment::all();
        self::assertCount(1, $payments);
        self::assertEquals(Payment::STATE_SENT, $payments->first()->state);
    }

    public function testHandle(): void
    {
        /** @var Collection|Payment[] $newPayments */
        $newPayments = factory(Payment::class)
            ->times(9)
            ->create(['state' => Payment::STATE_NEW]);

        $newPayments->each(function (Payment $payment) {
            factory(EventLog::class)->times(5)->create([
                'payment_id' => $payment->id,
                'paid_amount' => 100,
            ]);
        });

        $this->app->bind(
            Ads::class,
            function () {
                $ads = $this->createMock(Ads::class);
                $ads->method('sendPayments')->willReturn((new GetTransactionResponse($this->getRawData()))->getTx());

                return $ads;
            }
        );

        $this->artisan('ops:demand:payments:send')
            ->expectsOutput('Found 9 sendable payments.')
            ->assertExitCode(0);

        $payments = Payment::all();
        self::assertCount(9, $payments);

        $payments->each(function (Payment $payment) {
            self::assertEquals(Payment::STATE_SENT, $payment->state);
        });
    }

    public function testLock(): void
    {
        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        $this->artisan('ops:demand:payments:send')->assertExitCode(DemandSendPayments::STATUS_LOCKED);
    }

    public function testAdsError(): void
    {
        factory(Payment::class)->create(['state' => Payment::STATE_NEW, 'fee' => 1]);

        $this->app->bind(
            Ads::class,
            function () {
                $ads = $this->createMock(Ads::class);
                $ads->method('sendPayments')->willThrowException(new AdsException('test-exception'));

                return $ads;
            }
        );

        $this->artisan('ops:demand:payments:send')
            ->expectsOutput('Found 1 sendable payments.')
            ->assertExitCode(DemandSendPayments::STATUS_ERROR_ADS);

        $payments = Payment::all();
        self::assertCount(1, $payments);
        self::assertEquals(Payment::STATE_NEW, $payments->first()->state);
    }

    private function getRawData(): array
    {
        return json_decode($this->getTxSendMany(), true);
    }

    private function getTxSendMany(): string
    {
        return '{
            "current_block_time": "1539179488",
            "previous_block_time": "1539179456",
            "tx": {
                "data": "14010000000000F403BE5B0100850000000100",
                "signature": "F501E9507021B49423DE3CBF4BBA7829145D2595873F75070B8A5F1A1D0E71E7BC'
            . '999CA737DA2E5A4B2DFC296D2F441078D47081D10A2C9249BDFE278DB1120F",
                "time": "1539179508",
                "account_msid": "0",
                "account_hashin": "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF",
                "account_hashout": "A88AB7CA15CFB52E3EB6C4E4DC0BA1FC09A7FBC4920553CF605D37E6185BEF91",
                "deduct": "0.00000000000",
                "fee": "0.00000000000"
            },
            "network_tx": {
                "id": "0001:00000085:0001",
                "block_time": "1539178432",
                "block_id": "5BBDFFC0",
                "node": "1",
                "node_msid": "133",
                "node_mpos": "1",
                "size": "95",
                "hashpath_size": "7",
                "data": "0501000000000003000000CDFFBD5B010001000000000000B864D94500000084966'
            . '1171A830B4412AE89A8B0C3A29E05D1AE2D521232529F2CCFD1626B8681D385884C3193'
            . 'D11D3993CD3F9AD90E210D362FEDBE5DCDC092DE8DDBAC4FDA07",
                "hashpath": [
                    "9638C9D17A7BDB5309C46D6B78365853230459AE606BA3181C94456022523F4C",
                    "1737AEDFD0F8EEB8E39BC3DF08C4FEE79197D0CAB7CAB682FE4F26E272DA380F",
                    "D20418CD071D63997A59391AB9BF1DE35C624C1B6000C21338305BEB378594E9",
                    "7670510D4E7D8AB620C07D2569115C76DD626DC073ECC203663FF0DB94B954AF",
                    "8EE49D07979D7891399F2E3B405E4E36CCCE2AE7E1EB88916A03C8EFB8E8F680",
                    "AF4FD5922526C6C8FA2506C45315B25E4A961099E7688A7C044630E44D056956",
                    "954AEDF00ABF157D1E01E678D84F8D903A392A9844C5C25D6A05726C9BCBEB5A"
                ]
            },
            "txn": {
                "type": "send_many",
                "node": "1",
                "user": "0",
                "sender_address": "0001-00000000-9B6F",
                "msg_id": "3",
                "time": "1539178445",
                "wire_count": "1",
                "sender_fee": "0.00150000000",
                "wires": [
                    {
                        "target_node": "1",
                        "target_user": "0",
                        "target_address": "0001-00000005-CBCA",
                        "amount": "3.00000000000"
                    }
                ],
                "signature": "849661171A830B4412AE89A8B0C3A29E05D1AE2D521232529F2CCFD1'
            . '626B8681D385884C3193D11D3993CD3F9AD90E210D362FEDBE5DCDC092DE8DDBAC4FDA07"
            }
        }';
    }
}
