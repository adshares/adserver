<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

declare(strict_types = 1);

namespace Adshares\Adserver\Tests\Http;

use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\Payment;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Demand\Application\Service\PaymentDetailsVerify;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use function uniqid;

final class DemandControllerTest extends TestCase
{
    use RefreshDatabase;

    private const PAYMENT_DETAIL_URL = '/payment-details';

    public function testPaymentDetailsWhenMoreThanOnePaymentExistsForGivenTransactionIdAndAddress(): void
    {
        $this->app->bind(
            PaymentDetailsVerify::class,
            function () {
                $signatureVerify = $this->createMock(PaymentDetailsVerify::class);

                $signatureVerify
                    ->expects($this->once())
                    ->method('verify')
                    ->willReturn(true);

                return $signatureVerify;
            }
        );

        $user = factory(User::class)->create();
        $user->is_advertiser = 1;
        $this->actingAs($user, 'api');

        $accountAddress = '0001-00000001-0001';
        $accountAddressDifferentUser = '0001-00000002-0001';

        $transactionId = '0001:00000001:0001';
        $date = '2018-01-01T10:10:00+00:00';

        $payment1 = factory(Payment::class)->create(['account_address' => $accountAddress, 'tx_id' => $transactionId]);
        $payment2 = factory(Payment::class)->create(['account_address' => $accountAddress, 'tx_id' => $transactionId]);
        $payment3 = factory(Payment::class)->create(['account_address' => $accountAddress, 'tx_id' => $transactionId]);
        $payment4 =
            factory(Payment::class)->create(
                ['account_address' => $accountAddressDifferentUser, 'tx_id' => $transactionId]
            );
        $payment5 =
            factory(Payment::class)->create(
                ['account_address' => $accountAddressDifferentUser, 'tx_id' => $transactionId]
            );

        factory(EventLog::class)->create(['payment_id' => $payment1]);
        factory(EventLog::class)->create(['payment_id' => $payment1]);
        factory(EventLog::class)->create(['payment_id' => $payment2]);
        factory(EventLog::class)->create(['payment_id' => $payment2]);
        factory(EventLog::class)->create(['payment_id' => $payment3]);
        factory(EventLog::class)->create(['payment_id' => $payment4]);
        factory(EventLog::class)->create(['payment_id' => $payment5]);

        $url = sprintf(
            '%s/%s/%s/%s/%s',
            self::PAYMENT_DETAIL_URL,
            $transactionId,
            $accountAddress,
            $date,
            sha1(uniqid())
        );

        $response = $this->getJson($url);
        $content = json_decode($response->getContent());

        $response->assertStatus(Response::HTTP_OK);
        $this->assertCount(5, $content);
    }
}
