<?php

/**
 * Copyright (c) 2018-2024 Adshares sp. z o.o.
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
use Adshares\Adserver\Models\AdsPaymentMeta;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkBoostPayment;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\NetworkCase;
use Adshares\Adserver\Models\NetworkCaseLogsHourlyMeta;
use Adshares\Adserver\Models\NetworkCasePayment;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\NetworkImpression;
use Adshares\Adserver\Models\NetworkPayment;
use Adshares\Adserver\Models\PublisherBoostLedgerEntry;
use Adshares\Adserver\Models\TurnoverEntry;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Services\PaymentDetailsProcessor;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Adserver\Utilities\DateUtils;
use Adshares\Adserver\ViewModel\ServerEventType;
use Adshares\Common\Application\Service\LicenseVault;
use Adshares\Common\Domain\ValueObject\NullUrl;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Adshares\Mock\Client\DummyDemandClient;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Adshares\Supply\Domain\ValueObject\Status;
use Adshares\Supply\Domain\ValueObject\TurnoverEntryType;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Response;

class SupplyProcessPaymentsTest extends ConsoleTestCase
{
    private const SIGNATURE = 'ops:supply:payments:process';
    private const TX_ID_SEND_MANY = '0001:00000085:0001';
    private const TX_ID_SEND_ONE = '0001:00000083:0001';

    public function testAdsProcessOutdated(): void
    {
        $demandClient = $this->getDummyDemandClient();
        $networkHost = self::registerHost($demandClient);

        $createdAt = new DateTimeImmutable('-30 hours');
        AdsPayment::factory()->create([
            'created_at' => $createdAt,
            'address' => $networkHost->address,
            'status' => AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE,
            'tx_time' => $createdAt->modify('-8 minutes'),
        ]);

        $this->artisan(self::SIGNATURE)->assertExitCode(0);

        $this->assertEquals(AdsPayment::STATUS_RESERVED, AdsPayment::all()->first()->status);
        self::assertAdPaymentProcessedEventDispatched();
    }

    public function testAdsProcessMissingHost(): void
    {
        AdsPayment::factory()->create([
            'txid' => self::TX_ID_SEND_ONE,
            'amount' => 100000000000,
            'address' => '0001-00000000-9B6F',
            'status' => AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE,
        ]);

        $this->artisan(self::SIGNATURE)->assertExitCode(0);

        $this->assertEquals(AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE, AdsPayment::all()->first()->status);
        self::assertAdPaymentProcessedEventDispatched();
    }

    public function testAdsProcessDepositWithoutUser(): void
    {
        $demandClient = $this->getDummyDemandClient();
        $networkHost = self::registerHost($demandClient);

        AdsPayment::factory()->create([
            'txid' => self::TX_ID_SEND_ONE,
            'amount' => 100000000000,
            'address' => $networkHost->address,
            'status' => AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE,
        ]);

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
        self::assertAdPaymentProcessedEventDispatched();
    }

    public function testAdsProcessEventPayment(): void
    {
        $demandClient = $this->getDummyDemandClient();
        $networkHost = self::registerHost($demandClient);

        $paymentDetails = $demandClient->fetchPaymentDetails('', '', 333, 0);
        [$totalAmount, $licenseFee, $operatorFee] = $this->computeIncomeFromPayment($paymentDetails);
        $publishersIncome = $totalAmount - $licenseFee - $operatorFee;

        AdsPayment::factory()->create([
            'txid' => self::TX_ID_SEND_MANY,
            'amount' => $totalAmount,
            'address' => $networkHost->address,
            'status' => AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE,
        ]);

        $this->artisan(self::SIGNATURE, ['--chunkSize' => 500])->assertExitCode(0);

        $this->assertEquals(AdsPayment::STATUS_EVENT_PAYMENT, AdsPayment::all()->first()->status);
        $this->assertEquals($totalAmount, NetworkCasePayment::sum('total_amount'));
        $this->assertEquals($licenseFee, NetworkPayment::sum('amount'));
        $this->assertGreaterThan(0, NetworkCaseLogsHourlyMeta::fetchInvalid()->count());
        self::assertAdPaymentProcessedEventDispatched(1);
        $expectedTurnoverEntries = [
            [
                'ads_address' => hex2bin('000100000004'),
                'amount' => $totalAmount,
                'type' => TurnoverEntryType::SspIncome,
            ],
            [
                'ads_address' => hex2bin('FFFF00000000'),
                'amount' => $licenseFee,
                'type' => TurnoverEntryType::SspLicenseFee,
            ],
            [
                'ads_address' => null,
                'amount' => $operatorFee,
                'type' => TurnoverEntryType::SspOperatorFee,
            ],
            [
                'ads_address' => null,
                'amount' => $publishersIncome,
                'type' => TurnoverEntryType::SspPublishersIncome,
            ],
        ];
        self::assertDatabaseCount(TurnoverEntry::class, count($expectedTurnoverEntries));
        foreach ($expectedTurnoverEntries as $expectedTurnoverEntry) {
            self::assertDatabaseHas(TurnoverEntry::class, $expectedTurnoverEntry);
        }
    }

    public function testAdsProcessEventPaymentWhileBoostIsPresent(): void
    {
        $demandClient = $this->getDummyDemandClient();
        $networkHost = self::registerHost($demandClient);

        $paymentDetails = $demandClient->fetchPaymentDetails('', '', 1, 0);
        $totalAmount = 11000;
        $licenseFee = 110;
        $operatorFee = 108;
        $publishersIncome = 10782;

        $networkCampaign = NetworkCampaign::factory()->create();
        $user1 = User::factory()->create();
        $user1->uuid = 'fa9611d2d2f74e3f89c0e18b7c401891';
        $user1->save();
        $user2 = User::factory()->create();
        $user2->uuid = 'd5f5deefd010449ab0ee0e5e6b884090';
        $user2->save();

        NetworkCase::factory()->create([
            'campaign_id' => $networkCampaign->uuid,
            'case_id' => $paymentDetails[0]['case_id'],
            'network_impression_id' => NetworkImpression::factory()->create(),
            'publisher_id' => $user1->uuid,
        ]);
        NetworkCase::factory()->create([
            'campaign_id' => $networkCampaign->uuid,
            'case_id' => $paymentDetails[1]['case_id'],
            'network_impression_id' => NetworkImpression::factory()->create(),
            'publisher_id' => $user2->uuid,
        ]);
        PublisherBoostLedgerEntry::factory()->create([
            'ads_address' => '0001-00000004-DBEB',
            'user_id' => $user1->id,
            'amount' => 5_000,
            'amount_left' => 5_000,
        ]);
        $oldDate = new DateTimeImmutable('-6 months');
        PublisherBoostLedgerEntry::factory()->create([
            'created_at' => $oldDate,
            'updated_at' => $oldDate,
            'ads_address' => '0001-00000004-DBEB',
            'user_id' => $user1->id,
            'amount' => 5_000,
            'amount_left' => 3_250,
        ]);

        AdsPayment::factory()->create([
            'txid' => self::TX_ID_SEND_MANY,
            'amount' => $totalAmount,
            'address' => $networkHost->address,
            'status' => AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE,
        ]);

        $this->artisan(self::SIGNATURE, ['--chunkSize' => 500])->assertExitCode(0);

        self::assertDatabaseHas(UserLedgerEntry::class, [
            'user_id' => $user1->id,
            'amount' => 2 * 981,
        ]);
        self::assertEquals(
            5_000 - 981,
            PublisherBoostLedgerEntry::getAvailableBoost($user1->id, '0001-00000004-DBEB'),
        );
        $expectedTurnoverEntries = [
            [
                'ads_address' => hex2bin('000100000004'),
                'amount' => $totalAmount,
                'type' => TurnoverEntryType::SspIncome,
            ],
            [
                'ads_address' => hex2bin('FFFF00000000'),
                'amount' => $licenseFee,
                'type' => TurnoverEntryType::SspLicenseFee,
            ],
            [
                'ads_address' => null,
                'amount' => $operatorFee,
                'type' => TurnoverEntryType::SspOperatorFee,
            ],
            [
                'ads_address' => null,
                'amount' => $publishersIncome,
                'type' => TurnoverEntryType::SspPublishersIncome,
            ],
            [
                'ads_address' => hex2bin('000100000004'),
                'amount' => 3_250,
                'type' => TurnoverEntryType::SspBoostOperatorIncome,
            ],
            [
                'ads_address' => hex2bin('000100000004'),
                'amount' => 981,
                'type' => TurnoverEntryType::SspBoostPublishersIncome,
            ],
        ];
        self::assertDatabaseCount(TurnoverEntry::class, count($expectedTurnoverEntries));
        foreach ($expectedTurnoverEntries as $expectedTurnoverEntry) {
            self::assertDatabaseHas(TurnoverEntry::class, $expectedTurnoverEntry);
        }
    }

    public function testAdsProcessBoostPayment(): void
    {
        $demandClient = $this->getDummyDemandClient();
        $networkHost = self::registerHost($demandClient);

        $boostDetails = $demandClient->fetchBoostDetails('', '', 4, 0);

        [$totalAmount, $licenseFee, $operatorFee] = $this->computeIncomeFromBoost($boostDetails);
        $publishersIncome = $totalAmount - $licenseFee - $operatorFee;

        $adsPayment = AdsPayment::factory()->create([
            'txid' => self::TX_ID_SEND_MANY,
            'amount' => $totalAmount,
            'address' => $networkHost->address,
            'status' => AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE,
            'tx_time' =>
                DateUtils::getDateTimeRoundedToNextHour(NetworkCase::first()->created_at)->modify('+5 minutes'),
        ]);

        $this->artisan(self::SIGNATURE, ['--chunkSize' => 500])->assertExitCode(0);

        self::assertEquals(AdsPayment::STATUS_EVENT_PAYMENT, AdsPayment::all()->first()->status);
        self::assertEquals($totalAmount, NetworkBoostPayment::sum('total_amount'));
        self::assertEquals($licenseFee, NetworkPayment::sum('amount'));
        self::assertEquals($publishersIncome, PublisherBoostLedgerEntry::sum('amount'));
        self::assertAdPaymentProcessedEventDispatched(1);
        $expectedTurnoverEntries = [
            [
                'ads_address' => hex2bin('000100000004'),
                'amount' => $totalAmount,
                'type' => TurnoverEntryType::SspIncome,
            ],
            [
                'ads_address' => hex2bin('FFFF00000000'),
                'amount' => $licenseFee,
                'type' => TurnoverEntryType::SspLicenseFee,
            ],
            [
                'ads_address' => null,
                'amount' => $operatorFee,
                'type' => TurnoverEntryType::SspOperatorFee,
            ],
            [
                'ads_address' => hex2bin('000100000004'),
                'amount' => $publishersIncome,
                'type' => TurnoverEntryType::SspBoostLocked,
            ],
        ];
        self::assertDatabaseCount(TurnoverEntry::class, count($expectedTurnoverEntries));
        foreach ($expectedTurnoverEntries as $expectedTurnoverEntry) {
            self::assertDatabaseHas(TurnoverEntry::class, $expectedTurnoverEntry);
        }
        self::assertDatabaseCount(AdsPaymentMeta::class, 1);
        $adsPaymentMeta = AdsPaymentMeta::first();
        self::assertEquals($adsPayment->id, $adsPaymentMeta->ads_payment_id);
        self::assertEquals(4, $adsPaymentMeta->meta['boost']['offset']);
    }

    /**
     * @dataProvider adsProcessAllocationDataProvider
     */
    public function testAdsProcessAllocation(int $reportedAmount): void
    {
        $demandClient = $this->getDummyDemandClient();
        $networkHost = self::registerHost($demandClient);

        $caseId = Uuid::v4()->hex();
        NetworkCase::factory()->create([
            'case_id' => $caseId,
            'publisher_id' => 'fa9611d2d2f74e3f89c0e18b7c401891',
        ]);
        NetworkCampaign::factory()
            ->count(2)
            ->create([
                'source_address' => '0001-00000004-DBEB',
            ])
            ->each(
                function (NetworkCampaign $campaign) {
                    NetworkBanner::factory()->create(['network_campaign_id' => $campaign->id]);
                }
            );

        $allocationAmount = 123_000_000_000;
        $demandClientMock = self::createMock(DemandClient::class);
        $demandClientMock->method('fetchPaymentDetailsMeta')->willReturn([
            'allocation' => [
                'count' => 1,
                'sum' => $reportedAmount,
            ],
            'boost' => [
                'count' => 0,
                'sum' => 0,
            ],
            'events' => [
                'count' => 0,
                'sum' => 0,
            ],
        ]);
        $demandClientMock->expects(self::never())->method('fetchPaymentDetails');
        $demandClientMock->expects(self::never())->method('fetchBoostDetails');
        $this->app->bind(DemandClient::class, fn() => $demandClientMock);

        $adsPayment = AdsPayment::factory()->create([
            'txid' => self::TX_ID_SEND_MANY,
            'amount' => $allocationAmount,
            'address' => $networkHost->address,
            'status' => AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE,
        ]);

        $this->artisan(self::SIGNATURE, ['--chunkSize' => 500])
            ->assertExitCode(0);

        self::assertEquals(AdsPayment::STATUS_EVENT_PAYMENT, AdsPayment::all()->first()->status);
        self::assertAdPaymentProcessedEventDispatched(1);
        $expectedTurnoverEntries = [
            [
                'ads_address' => hex2bin('000100000004'),
                'amount' => $allocationAmount,
                'type' => TurnoverEntryType::SspJoiningFeeRefund,
            ],
        ];
        self::assertDatabaseCount(TurnoverEntry::class, count($expectedTurnoverEntries));
        foreach ($expectedTurnoverEntries as $expectedTurnoverEntry) {
            self::assertDatabaseHas(TurnoverEntry::class, $expectedTurnoverEntry);
        }
        self::assertDatabaseCount(AdsPaymentMeta::class, 1);
        $adsPaymentMeta = AdsPaymentMeta::first();
        self::assertEquals($adsPayment->id, $adsPaymentMeta->ads_payment_id);
        self::assertDatabaseCount(NetworkBoostPayment::class, 2);
        self::assertDatabaseHas(NetworkBoostPayment::class, [
            'total_amount' => (int)floor($allocationAmount / 2),
        ]);
    }

    public function adsProcessAllocationDataProvider(): array
    {
        return [
            'equal to payment' => [123_000_000_000],
            'greater than payment' => [150_000_000_000],
        ];
    }

    public function testAdsProcessAllocationWhileNoActiveCampaigns(): void
    {
        $demandClient = $this->getDummyDemandClient();
        $networkHost = self::registerHost($demandClient);

        $caseId = Uuid::v4()->hex();
        NetworkCase::factory()->create([
            'case_id' => $caseId,
            'publisher_id' => 'fa9611d2d2f74e3f89c0e18b7c401891',
        ]);

        $allocationAmount = 123_000_000_000;
        $demandClientMock = self::createMock(DemandClient::class);
        $demandClientMock->method('fetchPaymentDetailsMeta')->willReturn([
            'allocation' => [
                'count' => 1,
                'sum' => $allocationAmount,
            ],
            'boost' => [
                'count' => 0,
                'sum' => 0,
            ],
            'events' => [
                'count' => 0,
                'sum' => 0,
            ],
        ]);
        $demandClientMock->expects(self::never())->method('fetchPaymentDetails');
        $demandClientMock->expects(self::never())->method('fetchBoostDetails');
        $this->app->bind(DemandClient::class, fn() => $demandClientMock);

        $adsPayment = AdsPayment::factory()->create([
            'txid' => self::TX_ID_SEND_MANY,
            'amount' => $allocationAmount,
            'address' => $networkHost->address,
            'status' => AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE,
        ]);

        $this->artisan(self::SIGNATURE, ['--chunkSize' => 500])
            ->assertExitCode(0);

        self::assertEquals(AdsPayment::STATUS_EVENT_PAYMENT, AdsPayment::all()->first()->status);
        self::assertAdPaymentProcessedEventDispatched(1);
        $expectedTurnoverEntries = [
            [
                'ads_address' => hex2bin('000100000004'),
                'amount' => $allocationAmount,
                'type' => TurnoverEntryType::SspJoiningFeeRefund,
            ],
        ];
        self::assertDatabaseCount(TurnoverEntry::class, count($expectedTurnoverEntries));
        foreach ($expectedTurnoverEntries as $expectedTurnoverEntry) {
            self::assertDatabaseHas(TurnoverEntry::class, $expectedTurnoverEntry);
        }
        self::assertDatabaseCount(AdsPaymentMeta::class, 1);
        $adsPaymentMeta = AdsPaymentMeta::first();
        self::assertEquals($adsPayment->id, $adsPaymentMeta->ads_payment_id);
        self::assertDatabaseEmpty(NetworkBoostPayment::class);
    }

    public function testAdsProcessEventPaymentWhileDetailsCountIsEqualToLimit(): void
    {
        $demandClient = $this->getDummyDemandClient();
        $networkHost = self::registerHost($demandClient);

        $paymentDetails = $demandClient->fetchPaymentDetails('', '', 50, 0);
        [$totalAmount, $licenseFee, $operatorFee] = $this->computeIncomeFromPayment($paymentDetails);
        $publishersIncome = $totalAmount - $licenseFee - $operatorFee;

        AdsPayment::factory()->create([
            'txid' => self::TX_ID_SEND_MANY,
            'amount' => $totalAmount,
            'address' => $networkHost->address,
            'status' => AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE,
        ]);

        $this->artisan(self::SIGNATURE, ['--chunkSize' => 100])
            ->assertExitCode(0);

        $this->assertEquals(AdsPayment::STATUS_EVENT_PAYMENT, AdsPayment::all()->first()->status);
        $this->assertEquals($totalAmount, NetworkCasePayment::sum('total_amount'));
        $this->assertEquals($licenseFee, NetworkPayment::sum('amount'));
        $this->assertGreaterThan(0, NetworkCaseLogsHourlyMeta::fetchInvalid()->count());
        self::assertAdPaymentProcessedEventDispatched(1);
        $expectedTurnoverEntries = [
            [
                'ads_address' => hex2bin('000100000004'),
                'amount' => $totalAmount,
                'type' => TurnoverEntryType::SspIncome,
            ],
            [
                'ads_address' => hex2bin('FFFF00000000'),
                'amount' => $licenseFee,
                'type' => TurnoverEntryType::SspLicenseFee,
            ],
            [
                'ads_address' => null,
                'amount' => $operatorFee,
                'type' => TurnoverEntryType::SspOperatorFee,
            ],
            [
                'ads_address' => null,
                'amount' => $publishersIncome,
                'type' => TurnoverEntryType::SspPublishersIncome,
            ],
        ];
        self::assertDatabaseCount(TurnoverEntry::class, count($expectedTurnoverEntries));
        foreach ($expectedTurnoverEntries as $expectedTurnoverEntry) {
            self::assertDatabaseHas(TurnoverEntry::class, $expectedTurnoverEntry);
        }
    }

    public function testAdsProcessEventZeroPayment(): void
    {
        $demandClient = $this->getDummyDemandClient();
        $networkHost = self::registerHost($demandClient);

        $networkImpression = NetworkImpression::factory()->create();
        $paymentDetails = $demandClient->fetchPaymentDetails('', '', 100, 0);

        foreach ($paymentDetails as $paymentDetail) {
            $publisherId = $paymentDetail['publisher_id'];

            NetworkCase::factory()->create(
                [
                    'case_id' => $paymentDetail['case_id'],
                    'network_impression_id' => $networkImpression,
                    'publisher_id' => $publisherId,
                ]
            );
        }

        AdsPayment::factory()->create([
            'txid' => self::TX_ID_SEND_MANY,
            'amount' => 0,
            'address' => $networkHost->address,
            'status' => AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE,
        ]);

        $this->artisan(self::SIGNATURE, ['--chunkSize' => 500])->assertExitCode(0);

        $this->assertEquals(AdsPayment::STATUS_EVENT_PAYMENT, AdsPayment::all()->first()->status);
        $this->assertEquals(0, NetworkCasePayment::sum('total_amount'));
        $this->assertEquals(0, NetworkPayment::sum('amount'));
        $this->assertGreaterThan(0, NetworkCaseLogsHourlyMeta::fetchInvalid()->count());
        self::assertAdPaymentProcessedEventDispatched(1);
        self::assertDatabaseEmpty(TurnoverEntry::class);
    }

    public function testAdsProcessEventPaymentWithPaymentProcessorError(): void
    {
        $paymentDetailsProcessor = self::createMock(PaymentDetailsProcessor::class);
        $paymentDetailsProcessor->method('processPaidEvents')->willThrowException(new RuntimeException());
        $this->app->bind(PaymentDetailsProcessor::class, fn() => $paymentDetailsProcessor);

        $demandClient = $this->getDummyDemandClient();
        $networkHost = self::registerHost($demandClient);
        $networkImpression = NetworkImpression::factory()->create();
        $paymentDetail = $demandClient->fetchPaymentDetails('', '', 1, 0)[0];

        NetworkCase::factory()->create([
            'case_id' => $paymentDetail['case_id'],
            'network_impression_id' => $networkImpression->id,
            'publisher_id' => $paymentDetail['publisher_id'],
        ]);

        AdsPayment::factory()->create([
            'txid' => self::TX_ID_SEND_MANY,
            'amount' => (int)$paymentDetail['event_value'],
            'address' => $networkHost->address,
            'status' => AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE,
        ]);

        self::artisan(self::SIGNATURE)->assertExitCode(0);

        self::assertEquals(AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE, AdsPayment::all()->first()->status);
        self::assertEquals(0, NetworkCasePayment::sum('total_amount'));
        self::assertEquals(0, NetworkPayment::sum('amount'));
        self::assertEquals(0, NetworkCaseLogsHourlyMeta::fetchInvalid()->count());
        self::assertAdPaymentProcessedEventDispatched();
    }

    public function testAdsProcessEventPaymentWithServerError(): void
    {
        $demandClient = $this->getDummyDemandClient();
        $networkHost = self::registerHost($demandClient);

        $networkImpression = NetworkImpression::factory()->create();
        $paymentDetails = $demandClient->fetchPaymentDetails('', '', 333, 0);

        $publisherIds = [];
        $totalAmount = 0;
        $licenseFee = 0;

        /** @var LicenseReader $licenseReader */
        $licenseReader = app()->make(LicenseReader::class);
        $licenseFeeCoefficient = $licenseReader->getFee(LicenseReader::LICENSE_RX_FEE);

        foreach ($paymentDetails as $paymentDetail) {
            $publisherId = $paymentDetail['publisher_id'];

            NetworkCase::factory()->create(
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
            User::factory()->create(['uuid' => $publisherId]);
        }

        AdsPayment::factory()->create([
            'txid' => self::TX_ID_SEND_MANY,
            'amount' => $totalAmount,
            'address' => $networkHost->address,
            'status' => AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE,
        ]);

        $this->app->bind(
            DemandClient::class,
            function () {
                $callCount = 0;
                $dummyDemandClient = new DummyDemandClient();

                $demandClient = $this->createMock(DemandClient::class);
                $demandClient->method('fetchPaymentDetailsMeta')->willReturnCallback(
                    fn(string $host, string $transactionId) => $dummyDemandClient->fetchPaymentDetailsMeta(
                        $host,
                        $transactionId,
                    )
                );
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

        $this->artisan(self::SIGNATURE, ['--chunkSize' => 500])->assertExitCode(0);
        $this->assertEquals(AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE, AdsPayment::all()->first()->status);

        $this->artisan(self::SIGNATURE, ['--chunkSize' => 500])->assertExitCode(0);
        $this->assertEquals(AdsPayment::STATUS_EVENT_PAYMENT, AdsPayment::all()->first()->status);
        $this->assertEquals($totalAmount, NetworkCasePayment::sum('total_amount'));
        $this->assertEquals($licenseFee, NetworkPayment::sum('amount'));
        $this->assertGreaterThan(0, NetworkCaseLogsHourlyMeta::fetchInvalid()->count());
        self::assertAdPaymentProcessedEventDispatched();
    }

    public function testAdsProcessEventPaymentWithNoLicense(): void
    {
        $demandClient = $this->getDummyDemandClient();
        $networkHost = self::registerHost($demandClient);

        /** @var NetworkImpression $networkImpression */
        $networkImpression = NetworkImpression::factory()->create();
        $paymentDetail = $demandClient->fetchPaymentDetails('', '', 1, 0)[0];

        $licenseVault = self::createMock(LicenseVault::class);
        $licenseVault->method('read')->willThrowException(new RuntimeException('test-exception'));
        $licenseReader = new LicenseReader($licenseVault);
        $this->instance(LicenseReader::class, $licenseReader);
        Config::updateAdminSettings([Config::OPERATOR_RX_FEE => '0']);

        NetworkCase::factory()->create(
            [
                'case_id' => $paymentDetail['case_id'],
                'network_impression_id' => $networkImpression->id,
                'publisher_id' => $paymentDetail['publisher_id'],
            ]
        );

        $totalAmount = (int)$paymentDetail['event_value'];

        AdsPayment::factory()->create([
            'txid' => self::TX_ID_SEND_MANY,
            'amount' => $totalAmount,
            'address' => $networkHost->address,
            'status' => AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE,
        ]);

        $this->artisan(self::SIGNATURE, ['--chunkSize' => 500])
            ->expectsOutput('No license payment')
            ->assertExitCode(0);

        $this->assertEquals(AdsPayment::STATUS_EVENT_PAYMENT, AdsPayment::all()->first()->status);
        $this->assertEquals($totalAmount, NetworkCasePayment::sum('total_amount'));
        $this->assertDatabaseCount(NetworkPayment::class, 0);
        self::assertAdPaymentProcessedEventDispatched(1);
        self::assertDatabaseCount(TurnoverEntry::class, 2);
        $expectedTurnoverEntries = [
            [
                'ads_address' => hex2bin('000100000004'),
                'amount' => $totalAmount,
                'type' => TurnoverEntryType::SspIncome,
            ],
            [
                'ads_address' => null,
                'amount' => $totalAmount,
                'type' => TurnoverEntryType::SspPublishersIncome,
            ],
        ];
        foreach ($expectedTurnoverEntries as $expectedTurnoverEntry) {
            self::assertDatabaseHas(TurnoverEntry::class, $expectedTurnoverEntry);
        }
    }

    public function testLock(): void
    {
        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        $this->artisan(self::SIGNATURE)->assertExitCode(0);
    }

    private function registerHost(DemandClient $demandClient): NetworkHost
    {
        $info = $demandClient->fetchInfo(new NullUrl());
        return NetworkHost::factory()->create([
            'address' => $info->getAdsAddress(),
            'info' => $info,
            'info_url' => $info->getServerUrl() . 'info.json',
        ]);
    }

    private static function assertAdPaymentProcessedEventDispatched(int $count = 0): void
    {
        self::assertServerEventDispatched(ServerEventType::IncomingAdPaymentProcessed, [
            'adsPaymentCount' => $count,
        ]);
    }

    private function computeIncomeFromPayment(array $paymentDetails): array
    {
        $networkImpression = NetworkImpression::factory()->create();

        $totalAmount = 0;
        $licenseFee = 0;
        $operatorFee = 0;

        /** @var LicenseReader $licenseReader */
        $licenseReader = app()->make(LicenseReader::class);
        $licenseFeeCoefficient = $licenseReader->getFee(LicenseReader::LICENSE_RX_FEE);
        $operatorFeeCoefficient = 0.01;

        foreach ($paymentDetails as $paymentDetail) {
            $publisherId = $paymentDetail['publisher_id'];

            NetworkCase::factory()->create(
                [
                    'case_id' => $paymentDetail['case_id'],
                    'network_impression_id' => $networkImpression,
                    'publisher_id' => $publisherId,
                ]
            );

            $eventValue = (int)$paymentDetail['event_value'];
            $totalAmount += $eventValue;
            $eventFee = (int)floor($eventValue * $licenseFeeCoefficient);
            $licenseFee += $eventFee;
            $operatorFee += (int)floor(($eventValue - $eventFee) * $operatorFeeCoefficient);
        }

        return [$totalAmount, $licenseFee, $operatorFee];
    }

    private function computeIncomeFromBoost(array $details): array
    {
        $networkImpression = NetworkImpression::factory()->create();

        $totalAmount = 0;
        $licenseFee = 0;
        $operatorFee = 0;

        /** @var LicenseReader $licenseReader */
        $licenseReader = app()->make(LicenseReader::class);
        $licenseFeeCoefficient = $licenseReader->getFee(LicenseReader::LICENSE_RX_FEE);
        $operatorFeeCoefficient = 0.01;

        /** @var User $publisher */
        $publisher = User::factory()->create();

        foreach ($details as $detail) {
            $networkCampaign = NetworkCampaign::factory()->create([
                'demand_campaign_id' => $detail['campaign_id'],
                'source_address' => '0001-00000004-DBEB',
                'status' => Status::STATUS_ACTIVE,
            ]);

            $value = (int)$detail['value'];
            $totalAmount += $value;
            $eventFee = (int)floor($value * $licenseFeeCoefficient);
            $licenseFee += $eventFee;
            $operatorFee += (int)floor(($value - $eventFee) * $operatorFeeCoefficient);

            NetworkCase::factory()->create([
                'campaign_id' => $networkCampaign->uuid,
                'case_id' => Uuid::v4()->hex(),
                'created_at' => new DateTimeImmutable('-1 hour'),
                'network_impression_id' => $networkImpression,
                'publisher_id' => $publisher->uuid,
            ]);
        }

        return [$totalAmount, $licenseFee, $operatorFee];
    }

    private function getDummyDemandClient(): DemandClient
    {
        $client = new DummyDemandClient();
        $client->reset();
        return $client;
    }
}
