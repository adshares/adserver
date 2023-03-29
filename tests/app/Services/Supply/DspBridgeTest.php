<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Services\Supply;

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\BridgePayment;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\NetworkCase;
use Adshares\Adserver\Models\NetworkCasePayment;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\NetworkImpression;
use Adshares\Adserver\Models\NetworkPayment;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Services\PaymentDetailsProcessor;
use Adshares\Adserver\Services\Supply\DspBridge;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Domain\ValueObject\NullUrl;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Adshares\Mock\Client\DummyDemandClient;
use Adshares\Supply\Application\Dto\FoundBanners;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Closure;
use DateTime;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use PDOException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

class DspBridgeTest extends TestCase
{
    public function testIsActiveWhileNotConfigured(): void
    {
        self::assertFalse(DspBridge::isActive());
    }

    public function testIsActiveWhileConfigured(): void
    {
        $this->initDspBridgeConfiguration();

        self::assertTrue(DspBridge::isActive());
    }

    public function testReplaceBridgeBanners(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'example.com/serve' => Http::response([
                [
                    'ext_id' => '1',
                    'request_id' => '0',
                    'serve_url' => 'https://example.com/serve/1',
                ]
            ]),
        ]);
        $initiallyFoundBanners = $this->getFoundBanners();
        $context = new ImpressionContext([], [], []);

        $foundBanners = (new DspBridge())->replaceBridgeBanners($initiallyFoundBanners, $context);

        self::assertCount(1, $foundBanners);
        $foundBanner = $foundBanners->first();
        self::assertEquals('3', $foundBanner['request_id']);
        self::assertStringContainsString('extid=1', $foundBanner['click_url']);
        self::assertStringContainsString('extid=1', $foundBanner['view_url']);
        Http::assertSentCount(1);
    }

    public function testReplaceBridgeBannersWithOptions(): void
    {
        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $request = json_decode($request->body(), true)['requests'][0];
            foreach (['mimes', 'topframe'] as $key) {
                self::assertArrayHasKey($key, $request);
            }
            self::assertTrue($request['topframe']);
            self::assertEquals(['image/jpeg', 'video/mp4'], $request['mimes']);
            return Http::response([
                [
                    'ext_id' => '1',
                    'request_id' => '0',
                    'serve_url' => 'https://example.com/serve/1',
                ]
            ]);
        });
        $initiallyFoundBanners = $this->getFoundBanners();
        $context = new ImpressionContext([], [], []);
        $zones = [
            [
                'id' => '0',
                'placementId' => $initiallyFoundBanners->first()['zone_id'],
                'options' => [
                    'banner_mime' => ['image/jpeg', 'video/mp4'],
                    'banner_type' => ['image', 'video'],
                    'cpa_only' => true,
                    'topframe' => true,
                ],
            ]
        ];

        $foundBanners = (new DspBridge())->replaceBridgeBanners($initiallyFoundBanners, $context, $zones);

        self::assertCount(1, $foundBanners);
        $foundBanner = $foundBanners->first();
        self::assertEquals('3', $foundBanner['request_id']);
        self::assertStringContainsString('extid=1', $foundBanner['click_url']);
        self::assertStringContainsString('extid=1', $foundBanner['view_url']);
        Http::assertSentCount(1);
    }

    public function testReplaceBridgeBannersWhileEmptyResponse(): void
    {
        Http::preventStrayRequests();
        Http::fake(['example.com/serve' => Http::response([])]);
        $initiallyFoundBanners = $this->getFoundBanners();
        $context = new ImpressionContext([], [], []);

        $foundBanners = (new DspBridge())->replaceBridgeBanners($initiallyFoundBanners, $context);

        self::assertCount(1, $foundBanners);
        self::assertNull($foundBanners->first());
        Http::assertSentCount(1);
    }

    public function testReplaceBridgeBannersWhileNoBannersFromBridge(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $initiallyFoundBanners = $this->getFoundBanners();
        $banner = $initiallyFoundBanners->get(0);
        $banner['pay_from'] = '0001-00000004-DBEB';
        $initiallyFoundBanners->set(0, $banner);
        $context = new ImpressionContext([], [], []);

        $foundBanners = (new DspBridge())->replaceBridgeBanners($initiallyFoundBanners, $context);

        self::assertCount(1, $foundBanners);
        self::assertEquals('3', $foundBanners->first()['request_id']);
        Http::assertNothingSent();
    }

    public function testReplaceBridgeBannersWhileInvalidStatus(): void
    {
        Http::preventStrayRequests();
        Http::fake(['example.com/serve' => Http::response(status: Response::HTTP_NOT_FOUND)]);
        $initiallyFoundBanners = $this->getFoundBanners();
        $context = new ImpressionContext([], [], []);

        $foundBanners = (new DspBridge())->replaceBridgeBanners($initiallyFoundBanners, $context);

        self::assertCount(1, $foundBanners);
        self::assertNull($foundBanners->first());
        Http::assertSentCount(1);
    }

    public function testReplaceBridgeBannersWhileConnectionException(): void
    {
        Http::fake(fn() => throw new ConnectionException('test-exception'));
        $initiallyFoundBanners = $this->getFoundBanners();
        $context = new ImpressionContext([], [], []);

        $foundBanners = (new DspBridge())->replaceBridgeBanners($initiallyFoundBanners, $context);

        self::assertCount(1, $foundBanners);
        self::assertNull($foundBanners->first());
    }

    /**
     * @dataProvider replaceBridgeBannersWhileInvalidResponseProvider
     */
    public function testReplaceBridgeBannersWhileInvalidResponse(mixed $response): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'example.com/serve' => Http::response($response),
        ]);
        $initiallyFoundBanners = $this->getFoundBanners();
        $context = new ImpressionContext([], [], []);

        $foundBanners = (new DspBridge())->replaceBridgeBanners($initiallyFoundBanners, $context);

        self::assertCount(1, $foundBanners);
        self::assertNull($foundBanners->first());
        Http::assertSentCount(1);
    }

    public function replaceBridgeBannersWhileInvalidResponseProvider(): array
    {
        return [
            'not existing request id' => [
                [
                    [
                        'request_id' => '1',
                        'ext_id' => '1',
                        'serve_url' => 'https://example.com/serve/1',
                    ]
                ]
            ],
            'no request id' => [
                [
                    [
                        'ext_id' => '1',
                        'serve_url' => 'https://example.com/serve/1',
                    ]
                ]
            ],
            'no ext id' => [
                [
                    [
                        'request_id' => '0',
                        'serve_url' => 'https://example.com/serve/1',
                    ]
                ]
            ],
            'no serve url' => [
                [
                    [
                        'request_id' => '0',
                        'ext_id' => '1',
                    ]
                ]
            ],
            'invalid serve url type' => [
                [
                    [
                        'request_id' => '0',
                        'ext_id' => '1',
                        'serve_url' => 1234,
                    ]
                ]
            ],
            'entry is not array' => [['0']],
            'content is not array' => ['0'],
        ];
    }

    /**
     * @dataProvider getEventRedirectUrlProvider
     */
    public function testGetEventRedirectUrl(Closure $responseClosure, ?string $expectedUrl): void
    {
        Http::preventStrayRequests();
        Http::fake(['example.com' => $responseClosure()]);

        $url = (new DspBridge())->getEventRedirectUrl('https://example.com');

        self::assertEquals($expectedUrl, $url);
        Http::assertSentCount(1);
    }

    public function getEventRedirectUrlProvider(): array
    {
        return [
            'redirection' => [
                fn() => Http::response(['redirect_url' => 'https://adshares.net']),
                'https://adshares.net',
            ],
            'no redirection' => [fn() => Http::response(status: Response::HTTP_NO_CONTENT), null],
            'unsupported response format' => [fn() => Http::response(['url' => 'https://adshares.net']), null],
        ];
    }

    public function testGetEventRedirectUrlWhileConnectionException(): void
    {
        Http::fake(fn() => throw new ConnectionException('test-exception'));

        $url = (new DspBridge())->getEventRedirectUrl('https://example.com');

        self::assertEquals(null, $url);
    }

    public function testFetchAndStorePayments(): void
    {
        BridgePayment::factory()->create([
            'payment_id' => '1678957200',
            'payment_time' => '2023-03-17 14:00:00',
            'status' => BridgePayment::STATUS_RETRY,
            'amount' => null,
        ]);
        $responseData = [
            [
                'id' => '1678953600',
                'created_at' => '2023-03-17T16:04:33+00:00',
                'updated_at' => '2023-03-17T16:04:33+00:00',
                'status' => 'done',
                'value' => 100_000_000_000,
            ],
            [
                'id' => '1678957200',
                'created_at' => '2023-03-17T16:04:33+00:00',
                'updated_at' => '2023-03-17T16:04:33+00:00',
                'status' => 'done',
                'value' => 123_400_000_000,
            ],
            [
                'id' => '1678960800',
                'created_at' => '2023-03-17T16:04:33+00:00',
                'updated_at' => '2023-03-17T16:04:33+00:00',
                'status' => 'error',
                'value' => null,
            ],
            [
                'id' => '1678964400',
                'created_at' => '2023-03-17T16:04:33+00:00',
                'updated_at' => '2023-03-17T16:04:33+00:00',
                'status' => 'processing',
                'value' => null,
            ],
        ];
        $this->initDspBridgeConfiguration();
        Http::preventStrayRequests();
        Http::fake(['example.com/payment-reports' => Http::response($responseData)]);

        (new DspBridge())->fetchAndStorePayments();

        self::assertDatabaseHas(
            BridgePayment::class,
            [
                'payment_id' => '1678957200',
                'payment_time' => '2023-03-17 16:04:33',
                'status' => BridgePayment::STATUS_NEW,
                'amount' => 123_400_000_000,
            ],
        );
        self::assertDatabaseHas(
            BridgePayment::class,
            [
                'payment_id' => '1678964400',
                'payment_time' => '2023-03-17 16:04:33',
                'status' => BridgePayment::STATUS_RETRY,
                'amount' => null,
            ],
        );
        Http::assertSentCount(1);
    }

    public function testFetchAndStorePaymentsWhileResponseIsEmpty(): void
    {
        $responseData = [];
        $this->initDspBridgeConfiguration();
        Http::preventStrayRequests();
        Http::fake(['example.com/payment-reports' => Http::response($responseData)]);

        (new DspBridge())->fetchAndStorePayments();

        self::assertDatabaseEmpty(BridgePayment::class);
        Http::assertSentCount(1);
    }

    public function testFetchAndStorePaymentsWhileConnectionException(): void
    {
        Log::spy();
        $this->initDspBridgeConfiguration();
        Http::fake(fn() => throw new ConnectionException('test-exception'));

        (new DspBridge())->fetchAndStorePayments();

        self::assertDatabaseEmpty(BridgePayment::class);
        Log::shouldHaveReceived('error')
            ->with('Fetching payments from bridge failed: test-exception')
            ->once();
    }

    /**
     * @dataProvider fetchAndStorePaymentsWhileInvalidResponseProvider
     */
    public function testFetchAndStorePaymentsWhileInvalidResponse(mixed $response, string $errorMessage): void
    {
        Log::spy();
        Http::preventStrayRequests();
        Http::fake([
            'example.com/payment-reports' => Http::response($response),
        ]);
        $this->initDspBridgeConfiguration();

        (new DspBridge())->fetchAndStorePayments();

        self::assertDatabaseEmpty(BridgePayment::class);
        Http::assertSentCount(1);
        Log::shouldHaveReceived('error')
            ->with($errorMessage)
            ->once();
    }

    public function fetchAndStorePaymentsWhileInvalidResponseProvider(): array
    {
        return [
            'no id' => [
                [self::validPaymentResponseEntry(remove: 'id')],
                'Invalid bridge payments response: missing key id',
            ],
            'no created_at' => [
                [self::validPaymentResponseEntry(remove: 'created_at')],
                'Invalid bridge payments response: missing key created_at',
            ],
            'no status' => [
                [self::validPaymentResponseEntry(remove: 'status')],
                'Invalid bridge payments response: missing key status',
            ],
            'no value' => [
                [self::validPaymentResponseEntry(remove: 'value')],
                'Invalid bridge payments response: missing key value',
            ],
            'invalid id type' => [
                [self::validPaymentResponseEntry(['id' => 1])],
                'Invalid bridge payments response: id is not a string',
            ],
            'invalid id too long' => [
                [self::validPaymentResponseEntry(['id' => '12345678901234567890'])],
                'Invalid bridge payments response: id must have at most 18 characters',
            ],
            'invalid created_at format' => [
                [self::validPaymentResponseEntry(['created_at' => '2023-01-01'])],
                'Invalid bridge payments response: created_at is not in ISO8601 format',
            ],
            'invalid status type' => [
                [self::validPaymentResponseEntry(['status' => 0])],
                'Invalid bridge payments response: status is not a string',
            ],
            'entry is not array' => [['0'], 'Invalid bridge payments response: entry is not an array'],
            'content is not array' => ['0', 'Invalid bridge payments response: body is not an array'],
        ];
    }

    public function testProcessPayments(): void
    {
        $bridgePayment = $this->initBridgePaymentWithHost();
        $paymentDetailsProcessor = $this->mockPaymentDetailsProcessor();
        $networkImpression = NetworkImpression::factory()->create();
        /** @var User $publisher */
        $publisher = User::factory()->create();
        $caseCount = 2;
        /** @var Collection<NetworkCase> $networkCases */
        $networkCases = NetworkCase::factory()->times($caseCount)->create([
            'network_impression_id' => $networkImpression,
            'publisher_id' => $publisher->uuid,
        ]);
        $demandClient = self::createMock(DemandClient::class);
        $demandClient->expects(self::once())->method('fetchPaymentDetails')->willReturn(
            $networkCases->map(
                fn($case) => [
                    'case_id' => $case->case_id,
                    'event_value' => (int)(1e11 / $caseCount),
                ],
            )->toArray()
        );
        $expectedPaidAmount = 1e11;
        $expectedLicenseFee = 0;

        (new DspBridge())->processPayments($demandClient, $paymentDetailsProcessor);

        self::assertDatabaseCount(BridgePayment::class, 1);
        self::assertDatabaseHas(BridgePayment::class, ['status' => BridgePayment::STATUS_DONE]);
        self::assertEquals($expectedPaidAmount, (new NetworkCasePayment())->sum('total_amount'));
        self::assertEquals($expectedLicenseFee, (new NetworkPayment())->sum('amount'));
        self::assertDatabaseHas(UserLedgerEntry::class, [
            'amount' => $expectedPaidAmount,
            'user_id' => $publisher->id,
        ]);
        self::assertDatabaseCount(NetworkCasePayment::class, $caseCount);
        self::assertDatabaseHas(NetworkCasePayment::class, [
            'bridge_payment_id' => $bridgePayment->id,
            'network_case_id' => $networkCases->first()->id,
        ]);
    }

    public function testProcessPaymentsRetryWhileDemandClientFailed(): void
    {
        $bridgePayment = $this->initBridgePaymentWithHost();
        $paymentDetailsProcessor = $this->mockPaymentDetailsProcessor();
        $networkImpression = NetworkImpression::factory()->create();
        /** @var User $publisher */
        $publisher = User::factory()->create();
        $caseCount = 2;
        /** @var Collection<NetworkCase> $networkCases */
        $networkCases = NetworkCase::factory()->times($caseCount)->create([
            'network_impression_id' => $networkImpression,
            'publisher_id' => $publisher->uuid,
        ]);
        $expectedPaidAmount = 1e11;
        $expectedLicenseFee = 0;
        $demandClient = self::createMock(DemandClient::class);
        $demandClient->expects(self::exactly(4))->method('fetchPaymentDetails')->willReturnOnConsecutiveCalls(
            [
                [
                    'case_id' => $networkCases->first()->case_id,
                    'event_value' => (int)($expectedPaidAmount / $caseCount),
                ]
            ],
            $this->throwException(new UnexpectedClientResponseException('test-exception')),
            [
                [
                    'case_id' => $networkCases->last()->case_id,
                    'event_value' => (int)($expectedPaidAmount / $caseCount),
                ]
            ],
            [],
        );

        (new DspBridge())->processPayments($demandClient, $paymentDetailsProcessor, 1);

        self::assertEquals(1, $bridgePayment->refresh()->last_offset);

        (new DspBridge())->processPayments($demandClient, $paymentDetailsProcessor, 1);

        self::assertDatabaseCount(BridgePayment::class, 1);
        self::assertDatabaseHas(BridgePayment::class, ['status' => BridgePayment::STATUS_DONE]);
        self::assertEquals($expectedPaidAmount, (new NetworkCasePayment())->sum('total_amount'));
        self::assertEquals($expectedLicenseFee, (new NetworkPayment())->sum('amount'));
        self::assertDatabaseHas(UserLedgerEntry::class, [
            'amount' => $expectedPaidAmount,
            'user_id' => $publisher->id,
        ]);
        self::assertDatabaseCount(NetworkCasePayment::class, $caseCount);
        self::assertDatabaseHas(NetworkCasePayment::class, [
            'bridge_payment_id' => $bridgePayment->id,
            'network_case_id' => $networkCases->first()->id,
        ]);
    }

    public function testProcessPaymentsWhilePaymentCannotBeAddedToPublisherAccount(): void
    {
        $this->initBridgePaymentWithHost();
        $exchangeRateReader = self::createMock(ExchangeRateReader::class);
        $exchangeRateReader->method('fetchExchangeRate')
            ->willReturn(new ExchangeRate(new DateTime(), 1, 'USD'));
        $licenseReader = self::createMock(LicenseReader::class);
        $paymentDetailsProcessor = Mockery::mock(new PaymentDetailsProcessor($exchangeRateReader, $licenseReader));
        $paymentDetailsProcessor->shouldReceive('addBridgeAdIncomeToUserLedger')
            ->andThrow(new PDOException('test-exception'));
        $networkImpression = NetworkImpression::factory()->create();
        /** @var User $publisher */
        $publisher = User::factory()->create();
        $caseCount = 2;
        /** @var Collection<NetworkCase> $networkCases */
        $networkCases = NetworkCase::factory()->times($caseCount)->create([
            'network_impression_id' => $networkImpression,
            'publisher_id' => $publisher->uuid,
        ]);
        $demandClient = self::createMock(DemandClient::class);
        $demandClient->expects(self::once())->method('fetchPaymentDetails')->willReturn(
            $networkCases->map(
                fn($case) => [
                    'case_id' => $case->case_id,
                    'event_value' => (int)(1e11 / $caseCount),
                ],
            )->toArray()
        );

        (new DspBridge())->processPayments($demandClient, $paymentDetailsProcessor);

        self::assertDatabaseCount(BridgePayment::class, 1);
        self::assertDatabaseHas(BridgePayment::class, ['status' => BridgePayment::STATUS_NEW]);
        self::assertDatabaseEmpty(UserLedgerEntry::class);
        self::assertDatabaseEmpty(NetworkCasePayment::class);
        self::assertDatabaseEmpty(NetworkPayment::class);
    }

    public function testProcessPaymentsWhileHostDeleted(): void
    {
        $this->initBridgePaymentWithHost();
        NetworkHost::first()->delete();
        $paymentDetailsProcessor = self::createMock(PaymentDetailsProcessor::class);
        $demandClient = self::createMock(DemandClient::class);

        (new DspBridge())->processPayments($demandClient, $paymentDetailsProcessor);

        self::assertDatabaseCount(BridgePayment::class, 1);
        self::assertDatabaseHas(BridgePayment::class, ['status' => BridgePayment::STATUS_INVALID]);
        self::assertDatabaseEmpty(UserLedgerEntry::class);
        self::assertDatabaseEmpty(NetworkCasePayment::class);
    }

    private function initDspBridgeConfiguration(array $settings = []): void
    {
        $mergedSettings = array_merge(
            [
                Config::DSP_BRIDGE_ACCOUNT_ADDRESS => '0001-00000001-8B4E',
                Config::DSP_BRIDGE_URL => 'https://example.com',
            ],
            $settings,
        );
        Config::updateAdminSettings($mergedSettings);
        DatabaseConfigReader::overwriteAdministrationConfig();
    }

    private function getFoundBanners(): FoundBanners
    {
        $this->initDspBridgeConfiguration();
        NetworkHost::factory()->create([
            'address' => '0001-00000001-8B4E',
            'host' => 'https://example.com',
        ]);
        /** @var Zone $zone */
        $zone = Zone::factory()->create([
            'site_id' => Site::factory()->create([
                'user_id' => User::factory()->create(['api_token' => '1234', 'auto_withdrawal' => 1e11]),
                'status' => Site::STATUS_ACTIVE,
            ]),
        ]);
        /** @var NetworkBanner $networkBanner */
        $networkBanner = NetworkBanner::factory()->create([
            'network_campaign_id' => NetworkCampaign::factory()->create(),
            'serve_url' => 'https://example.com/serve/' . Uuid::uuid4()->toString(),
        ]);
        $impressionId = Uuid::uuid4();

        return new FoundBanners([
            [
                'id' => $networkBanner->uuid,
                'demand_id' => $networkBanner->demand_banner_id,
                'publisher_id' => '0123456879ABCDEF0123456879ABCDEF',
                'zone_id' => $zone->uuid,
                'pay_from' => '0001-00000001-8B4E',
                'pay_to' => AdsUtils::normalizeAddress(config('app.adshares_address')),
                'type' => $networkBanner->type,
                'size' => $networkBanner->size,
                'serve_url' => $networkBanner->serve_url,
                'creative_sha1' => '',
                'click_url' => SecureUrl::change(
                    route(
                        'log-network-click',
                        [
                            'id' => $networkBanner->uuid,
                            'iid' => $impressionId,
                            'r' => Utils::urlSafeBase64Encode($networkBanner->click_url),
                            'zid' => $zone->uuid,
                        ]
                    )
                ),
                'view_url' => SecureUrl::change(
                    route(
                        'log-network-view',
                        [
                            'id' => $networkBanner->uuid,
                            'iid' => $impressionId,
                            'r' => Utils::urlSafeBase64Encode($networkBanner->view_url),
                            'zid' => $zone->uuid,
                        ]
                    )
                ),
                'info_box' => true,
                'rpm' => 0.5,
                'request_id' => '3',
            ]
        ]);
    }

    private static function validPaymentResponseEntry(array $merge = [], string $remove = null): array
    {
        $data = array_merge([
            'id' => '1678953600',
            'created_at' => '2023-03-17T16:04:33+00:00',
            'updated_at' => '2023-03-17T16:04:33+00:00',
            'status' => 'done',
            'value' => 100_000_000_000,
        ], $merge);
        if (null !== $remove) {
            unset($data[$remove]);
        }
        return $data;
    }

    private function initBridgePaymentWithHost(): BridgePayment
    {
        $info = (new DummyDemandClient())->fetchInfo(new NullUrl());
        /** @var NetworkHost $networkHost */
        $networkHost = NetworkHost::factory()->create([
            'address' => '0001-00000000-9B6F',
            'info' => $info,
            'info_url' => $info->getServerUrl() . 'info.json',
        ]);
        return BridgePayment::factory()->create(['address' => $networkHost->address]);
    }

    private function mockPaymentDetailsProcessor(): PaymentDetailsProcessor
    {
        $exchangeRateReader = self::createMock(ExchangeRateReader::class);
        $exchangeRateReader->method('fetchExchangeRate')
            ->willReturn(new ExchangeRate(new DateTime(), 1, 'USD'));
        $licenseReader = self::createMock(LicenseReader::class);
        return new PaymentDetailsProcessor($exchangeRateReader, $licenseReader);
    }
}
