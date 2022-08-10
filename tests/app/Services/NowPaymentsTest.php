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

namespace Adshares\Adserver\Tests\Services;

use Adshares\Adserver\Mail\DepositProcessed;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Services\NowPayments;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Common\Application\Model\Currency;
use Illuminate\Support\Facades\Mail;

final class NowPaymentsTest extends TestCase
{
    public function testInfo(): void
    {
        $nowPayments = $this->setupNowPayments();

        self::assertEquals(
            [
                'min_amount' => 32,
                'max_amount' => 1024,
                'exchange_rate' => 0.6666,
                'currency' => 'USD',
            ],
            $nowPayments->info()
        );
    }

    public function testInfoNoConfiguration(): void
    {
        $nowPayments = $this->app->make(NowPayments::class);

        self::assertNull($nowPayments->info());
    }

    /**
     * @dataProvider exchangeCurrencyProvider
     */
    public function testExchange(Currency $currency, string $targetAmount, int $expectedAmount): void
    {
        Config::updateAdminSettings([Config::CURRENCY => $currency->value]);
        $nowPayments = $this->setupNowPayments();
        $user = User::factory()->create();

        $result = $nowPayments->exchange(
            $user,
            ['orderId' => 'order_1', 'paymentId' => 'payment_2', 'targetAmount' => $targetAmount]
        );

        self::assertTrue($result);
        Mail::assertQueued(function (DepositProcessed $mail) use ($currency, $expectedAmount) {
            return $currency === $mail->getCurrency() && $expectedAmount === $mail->getAmount();
        });
    }

    /**
     * @dataProvider exchangeCurrencyProvider
     */
    public function testExchangeWhileLedgerExists(Currency $currency, string $targetAmount, int $expectedAmount): void
    {
        Config::updateAdminSettings([Config::CURRENCY => $currency->value]);
        $nowPayments = $this->setupNowPayments();
        /** @var User $user */
        $user = User::factory()->create();
        UserLedgerEntry::factory()->create(
            [
                'status' => UserLedgerEntry::STATUS_PROCESSING,
                'type' => UserLedgerEntry::TYPE_DEPOSIT,
                'txid' => 'NP:payment_2',
                'user_id' => $user->id,
            ]
        );

        $result = $nowPayments->exchange(
            $user,
            ['orderId' => 'order_1', 'paymentId' => 'payment_2', 'targetAmount' => $targetAmount]
        );

        self::assertTrue($result);
        Mail::assertQueued(function (DepositProcessed $mail) use ($currency, $expectedAmount) {
            return $currency === $mail->getCurrency() && $expectedAmount === $mail->getAmount();
        });
    }

    public function exchangeCurrencyProvider(): array
    {
        return [
            'ADS' => [
                Currency::ADS,
                '200',// target amount in ADS
                20_000_000_000_000,// accounted in ADS
            ],
            'USD' => [
                Currency::USD,
                '200',// target amount in ADS
                6_666_000_000_000,// accounted in currency
            ],
        ];
    }

    public function testExchangeWhileLedgerAccepted(): void
    {
        $nowPayments = $this->setupNowPayments();
        /** @var User $user */
        $user = User::factory()->create();
        UserLedgerEntry::factory()->create(
            [
                'status' => UserLedgerEntry::STATUS_ACCEPTED,
                'type' => UserLedgerEntry::TYPE_DEPOSIT,
                'txid' => 'NP:payment_2',
                'user_id' => $user->id,
            ]
        );

        $result = $nowPayments->exchange(
            $user,
            ['orderId' => 'order_1', 'paymentId' => 'payment_2', 'targetAmount' => '200']
        );

        self::assertFalse($result);
        Mail::assertNothingQueued();
    }

    public function testExchangeWhileAmountNotDefined(): void
    {
        $nowPayments = $this->setupNowPayments();
        $user = User::factory()->create();

        $result = $nowPayments->exchange($user, []);

        self::assertFalse($result);
        Mail::assertNothingQueued();
    }

    private function setupNowPayments(): NowPayments
    {
        Config::updateAdminSettings(
            [
                Config::NOW_PAYMENTS_API_KEY => 'api-key',
                Config::NOW_PAYMENTS_CURRENCY => 'USD',
                Config::NOW_PAYMENTS_FEE => '0.5',
                Config::NOW_PAYMENTS_MAX_AMOUNT => '1024',
                Config::NOW_PAYMENTS_MIN_AMOUNT => '32',
            ]
        );
        DatabaseConfigReader::overwriteAdministrationConfig();
        return $this->app->make(NowPayments::class);
    }
}
