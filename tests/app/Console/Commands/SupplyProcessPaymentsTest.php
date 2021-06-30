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

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\NetworkCase;
use Adshares\Adserver\Models\NetworkCaseLogsHourlyMeta;
use Adshares\Adserver\Models\NetworkCasePayment;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\NetworkImpression;
use Adshares\Adserver\Models\NetworkPayment;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Common\Domain\ValueObject\NullUrl;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Adshares\Mock\Client\DummyAdSelectClient;
use Adshares\Mock\Client\DummyDemandClient;
use Adshares\Supply\Application\Service\AdSelect;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use DateTimeImmutable;
use Illuminate\Http\Response;

class SupplyProcessPaymentsTest extends ConsoleTestCase
{
    private const SIGNATURE = 'ops:supply:payments:process';

    private const TX_ID_SEND_MANY = '0001:00000085:0001';

    private const TX_ID_SEND_ONE = '0001:00000083:0001';

    public function testAdsProcessOutdated(): void
    {
        $demandClient = new DummyDemandClient();
        $info = $demandClient->fetchInfo(new NullUrl());
        $networkHost = NetworkHost::registerHost('0001-00000000-9B6F', $info);

        $adsPayment = new AdsPayment();
        $createdAt = new DateTimeImmutable('-30 hours');
        $adsPayment->created_at = $createdAt;
        $adsPayment->txid = self::TX_ID_SEND_ONE;
        $adsPayment->amount = 100000000000;
        $adsPayment->address = $networkHost->address;
        $adsPayment->status = AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE;
        $adsPayment->tx_time = $createdAt->modify('-8 minutes');
        $adsPayment->save();

        $this->artisan(self::SIGNATURE)->assertExitCode(0);

        $this->assertEquals(AdsPayment::STATUS_RESERVED, AdsPayment::all()->first()->status);
    }

    public function testAdsProcessMissingHost(): void
    {
        $adsPayment = new AdsPayment();
        $adsPayment->txid = self::TX_ID_SEND_ONE;
        $adsPayment->amount = 100000000000;
        $adsPayment->address = '0001-00000000-9B6F';
        $adsPayment->status = AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE;
        $adsPayment->tx_time = new DateTimeImmutable('-8 minutes');
        $adsPayment->save();

        $this->artisan(self::SIGNATURE)->assertExitCode(0);

        $this->assertEquals(AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE, AdsPayment::all()->first()->status);
    }

    public function testAdsProcessDepositWithoutUser(): void
    {
        $demandClient = new DummyDemandClient();
        $info = $demandClient->fetchInfo(new NullUrl());
        $networkHost = NetworkHost::registerHost('0001-00000000-9B6F', $info);

        $adsPayment = new AdsPayment();
        $adsPayment->txid = self::TX_ID_SEND_ONE;
        $adsPayment->amount = 100000000000;
        $adsPayment->address = $networkHost->address;
        $adsPayment->status = AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE;
        $adsPayment->tx_time = new DateTimeImmutable('-8 minutes');
        $adsPayment->save();

        $this->app->bind(
            DemandClient::class,
            function () {
                $demandClient = $this->createMock(DemandClient::class);
                $demandClient->method('fetchPaymentDetails')->willThrowException(
                    new UnexpectedClientResponseException('', Response::HTTP_NOT_FOUND)
                );

                return $demandClient;
            }
        );

        $this->artisan(self::SIGNATURE)->assertExitCode(0);

        $this->assertEquals(AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE, AdsPayment::all()->first()->status);
    }

    public function testAdsProcessEventPayment(): void
    {
        $demandClient = new DummyDemandClient();
        $info = $demandClient->fetchInfo(new NullUrl());
        $networkHost = NetworkHost::registerHost('0001-00000000-9B6F', $info);

        $networkImpression = factory(NetworkImpression::class)->create();
        $paymentDetails = $demandClient->fetchPaymentDetails('', '', 333, 0);

        $publisherIds = [];
        $totalAmount = 0;
        $licenseFee = 0;

        /** @var LicenseReader $licenseReader */
        $licenseReader = app()->make(LicenseReader::class);
        $licenseFeeCoefficient = $licenseReader->getFee(Config::LICENCE_RX_FEE);

        foreach ($paymentDetails as $paymentDetail) {
            $publisherId = $paymentDetail['publisher_id'];

            factory(NetworkCase::class)->create(
                [
                    'case_id' => $paymentDetail['case_id'],
                    'network_impression_id' => $networkImpression->id,
                    'publisher_id' => $publisherId,
                ]
            );

            if (!in_array($publisherId, $publisherIds)) {
                $publisherIds[] = $publisherId;
            }

            $eventValue = (int)$paymentDetail['event_value'];
            $totalAmount += $eventValue;
            $licenseFee += (int)floor($eventValue * $licenseFeeCoefficient);
        }

        foreach ($publisherIds as $publisherId) {
            factory(User::class)->create(['uuid' => $publisherId]);
        }

        $adsPayment = new AdsPayment();
        $adsPayment->txid = self::TX_ID_SEND_MANY;
        $adsPayment->amount = $totalAmount;
        $adsPayment->address = $networkHost->address;
        $adsPayment->status = AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE;
        $adsPayment->tx_time = new DateTimeImmutable('-8 minutes');
        $adsPayment->save();

        $this->app->bind(
            DemandClient::class,
            function () {
                return new DummyDemandClient();
            }
        );

        $this->app->bind(
            AdSelect::class,
            function () {
                return new DummyAdSelectClient();
            }
        );

        $this->artisan(self::SIGNATURE, ['--chunkSize' => 500])->assertExitCode(0);

        $this->assertEquals(AdsPayment::STATUS_EVENT_PAYMENT, AdsPayment::all()->first()->status);
        $this->assertEquals($totalAmount, NetworkCasePayment::sum('total_amount'));
        $this->assertEquals($licenseFee, NetworkPayment::sum('amount'));
        $this->assertGreaterThan(0, NetworkCaseLogsHourlyMeta::fetchInvalid()->count());
    }

    public function testAdsProcessEventPaymentWithServerError(): void
    {
        $demandClient = new DummyDemandClient();
        $info = $demandClient->fetchInfo(new NullUrl());
        $networkHost = NetworkHost::registerHost('0001-00000000-9B6F', $info);

        $networkImpression = factory(NetworkImpression::class)->create();
        $paymentDetails = $demandClient->fetchPaymentDetails('', '', 333, 0);

        $publisherIds = [];
        $totalAmount = 0;
        $licenseFee = 0;

        /** @var LicenseReader $licenseReader */
        $licenseReader = app()->make(LicenseReader::class);
        $licenseFeeCoefficient = $licenseReader->getFee(Config::LICENCE_RX_FEE);

        foreach ($paymentDetails as $paymentDetail) {
            $publisherId = $paymentDetail['publisher_id'];

            factory(NetworkCase::class)->create(
                [
                    'case_id' => $paymentDetail['case_id'],
                    'network_impression_id' => $networkImpression->id,
                    'publisher_id' => $publisherId,
                ]
            );

            if (!in_array($publisherId, $publisherIds)) {
                $publisherIds[] = $publisherId;
            }

            $eventValue = (int)$paymentDetail['event_value'];
            $totalAmount += $eventValue;
            $licenseFee += (int)floor($eventValue * $licenseFeeCoefficient);
        }

        foreach ($publisherIds as $publisherId) {
            factory(User::class)->create(['uuid' => $publisherId]);
        }

        $adsPayment = new AdsPayment();
        $adsPayment->txid = self::TX_ID_SEND_MANY;
        $adsPayment->amount = $totalAmount;
        $adsPayment->address = $networkHost->address;
        $adsPayment->status = AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE;
        $adsPayment->tx_time = new DateTimeImmutable('-8 minutes');
        $adsPayment->save();

        $this->app->bind(
            DemandClient::class,
            function () {
                $callCount = 0;
                $dummyDemandClient = new DummyDemandClient();

                $demandClient = $this->createMock(DemandClient::class);
                $demandClient->method('fetchPaymentDetails')->willReturnCallback(
                    function (
                        string $host,
                        string $transactionId,
                        int $limit,
                        int $offset
                    ) use (
                        $dummyDemandClient,
                        &$callCount
                    ) {
                        $callCount++;

                        if ($callCount === 2) {
                            throw new UnexpectedClientResponseException('', Response::HTTP_INTERNAL_SERVER_ERROR);
                        }

                        return $dummyDemandClient->fetchPaymentDetails($host, $transactionId, $limit, $offset);
                    }
                );

                return $demandClient;
            }
        );

        $this->app->bind(
            AdSelect::class,
            function () {
                return new DummyAdSelectClient();
            }
        );

        $this->artisan(self::SIGNATURE, ['--chunkSize' => 500])->assertExitCode(0);
        $this->assertEquals(AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE, AdsPayment::all()->first()->status);

        $this->artisan(self::SIGNATURE, ['--chunkSize' => 500])->assertExitCode(0);
        $this->assertEquals(AdsPayment::STATUS_EVENT_PAYMENT, AdsPayment::all()->first()->status);
        $this->assertEquals($totalAmount, NetworkCasePayment::sum('total_amount'));
        $this->assertEquals($licenseFee, NetworkPayment::sum('amount'));
        $this->assertGreaterThan(0, NetworkCaseLogsHourlyMeta::fetchInvalid()->count());
    }

    public function testLock(): void
    {
        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        $this->artisan(self::SIGNATURE)->assertExitCode(0);
    }
}
