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

namespace Adshares\Adserver\Tests\Client;

use Adshares\Adserver\Client\GuzzleDemandClient;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Repository\Common\ClassifierExternalRepository;
use Adshares\Adserver\Services\Common\ClassifierExternalSignatureVerifier;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\AdsAuthenticator;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Common\Application\Service\SignatureVerifier;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\Url;
use Adshares\Supply\Application\Service\Exception\EmptyInventoryException;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Adshares\Supply\Domain\Model\Banner;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\ValueObject\Status;
use DateTime;
use DateTimeInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

class GuzzleDemandClientTest extends TestCase
{
    public function testFetchInfo(): void
    {
        $responseMock = self::createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(Response::HTTP_OK);
        $responseMock->method('getBody')->willReturn(self::getInfoJson());
        /** @var ResponseInterface $responseMock */
        $client = $this->getClientMock($responseMock);
        $demandClient = $this->createGuzzleDemandClient($client);

        $info = $demandClient->fetchInfo(new Url('https://example.com/info.json'));

        self::assertEquals('adserver', $info->getModule());
        self::assertEquals('0001-00000005-CBCA', $info->getAdsAddress());
    }

    public function testFetchInfoExceptionDueToInvalidStatusOfResponse(): void
    {
        $responseMock = self::createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(Response::HTTP_NOT_FOUND);
        /** @var ResponseInterface $responseMock */
        $client = $this->getClientMock($responseMock);
        $demandClient = $this->createGuzzleDemandClient($client);

        self::expectException(UnexpectedClientResponseException::class);
        $demandClient->fetchInfo(new Url('https://example.com/info.json'));
    }

    /**
     * @dataProvider invalidContentProvider
     */
    public function testFetchInfoExceptionDueToInvalidResponseContent($content): void
    {
        $responseMock = self::createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(Response::HTTP_OK);
        $responseMock->method('getBody')->willReturn($content);
        /** @var ResponseInterface $responseMock */
        $client = $this->getClientMock($responseMock);
        $demandClient = $this->createGuzzleDemandClient($client);

        self::expectException(UnexpectedClientResponseException::class);
        $demandClient->fetchInfo(new Url('https://example.com/info.json'));
    }

    public function invalidContentProvider(): array
    {
        return [
            'empty' => [''],
            'malformed' => ['{"mo'],
            'missing fields' => ['[]'],
        ];
    }

    public function testFetchInfoExceptionDueToClientException(): void
    {
        $client = self::createMock(Client::class);
        $client->expects(self::once())
            ->method('get')
            ->willThrowException(
                new RequestException('test exception', new Request('GET', 'https://example.com/info.json'))
            );
        /** @var Client $client */
        $demandClient = $this->createGuzzleDemandClient($client);

        self::expectException(UnexpectedClientResponseException::class);
        $demandClient->fetchInfo(new Url('https://example.com/info.json'));
    }

    public function testFetchAllInventory(): void
    {
        Config::updateAdminSettings([
            Config::ADS_TXT_CHECK_SUPPLY_ENABLED => '1',
            Config::ADS_TXT_DOMAIN => 'example.com',
        ]);
        DatabaseConfigReader::overwriteAdministrationConfig();
        $client = self::createMock(Client::class);
        $client->expects(self::once())
            ->method('get')
            ->willReturn(new GuzzleResponse(body: $this->getInventoryResponse()));
        /** @var Client $client */
        $demandClient = $this->createGuzzleDemandClient($client);

        $campaigns = $demandClient->fetchAllInventory(
            new AccountId('0001-00000004-DBEB'),
            'https://example.com',
            'https://app.example.com/inventory',
            true,
        );

        self::assertCount(1, $campaigns);
        /** @var Campaign $campaign */
        $campaign = $campaigns->first();
        self::assertEquals('12345678901234567890123456789012', $campaign->getDemandCampaignId());
        self::assertEquals(Status::STATUS_PROCESSING, $campaign->getStatus());

        self::assertEquals(
            DateTime::createFromFormat(DateTimeInterface::ATOM, '2023-01-01T00:00:00+00:00'),
            $campaign->getDateStart(),
        );
        self::assertNull($campaign->getDateEnd());
        self::assertEquals([], $campaign->getTargetingRequires());
        self::assertEquals([], $campaign->getTargetingExcludes());
        self::assertEquals('0001-00000004-DBEB', $campaign->getSourceAddress());
        self::assertEquals(371_250_000_000, $campaign->getBudget());
        self::assertEquals(0, $campaign->getMaxCpc());
        self::assertEquals(300_000_000_000, $campaign->getMaxCpm());
        self::assertEquals('web', $campaign->getMedium());
        self::assertNull($campaign->getVendor());

        $banners = $campaign->getBanners();
        self::assertCount(1, $banners);
        /** @var Banner $banner */
        $banner = $banners->first();
        self::assertEquals('0123456789abcdef0123456789abcdef', $banner->getDemandBannerId());
        self::assertEquals('image', $banner->getType());
        self::assertEquals('image/png', $banner->getMime());
        self::assertEquals($campaign->getId(), $banner->getCampaignId());
        self::assertEquals('300x250', $banner->getSize());
        self::assertEquals(Status::STATUS_PROCESSING, $banner->getStatus());
        self::assertEquals('fdf53fcb69012345678b6bbf69c33b348ebc6e85', $banner->getChecksum());
        self::assertEquals([], $banner->getClassification());
    }

    public function testFetchAllInventoryWhileDspRequiresAdsTxtButSspDoesNotSupportId(): void
    {
        Config::updateAdminSettings([Config::ADS_TXT_CHECK_SUPPLY_ENABLED => '0']);
        DatabaseConfigReader::overwriteAdministrationConfig();
        $client = self::createMock(Client::class);
        $client->expects(self::once())
            ->method('get')
            ->willReturn(new GuzzleResponse(body: $this->getInventoryResponse()));
        /** @var Client $client */
        $demandClient = $this->createGuzzleDemandClient($client);
        Log::shouldReceive('info')->with('[Inventory Importer] Reject campaign 12345678901234567890123456789012');

        $campaigns = $demandClient->fetchAllInventory(
            new AccountId('0001-00000004-DBEB'),
            'https://example.com',
            'https://app.example.com/inventory',
            true,
        );

        self::assertCount(0, $campaigns);
    }

    public function testFetchAllInventoryWhileInvalidCampaignData(): void
    {
        $client = self::createMock(Client::class);
        $client->expects(self::once())
            ->method('get')
            ->willReturn(
                new GuzzleResponse(
                    body: <<<JSON
[
    {
        "id": "12345678901234567890123456789012",
        "landing_url": "https://landing.example.com",
        "date_start": "2023-01-01T00:00:00+00:00",
        "date_end": null,
        "created_at": "2022-01-01T00:00:00+00:00",
        "updated_at": "2022-01-01T00:00:00+00:00",
        "medium": "web",
        "vendor": null,
        "max_cpc": 0,
        "max_cpm": 300000000000,
        "budget": 371250000000,
        "banners": []
    }
]
JSON
                )
            );
        /** @var Client $client */
        $demandClient = $this->createGuzzleDemandClient($client);

        $campaigns = $demandClient->fetchAllInventory(
            new AccountId('0001-00000004-DBEB'),
            'https://example.com',
            'https://app.example.com/inventory',
            false,
        );

        self::assertEmpty($campaigns);
    }

    public function testFetchAllInventoryWhileClientException(): void
    {
        $client = self::createMock(Client::class);
        $client->expects(self::once())
            ->method('get')
            ->willThrowException(
                new RequestException('test exception', new Request('GET', 'https://app.example.com/inventory'))
            );
        /** @var Client $client */
        $demandClient = $this->createGuzzleDemandClient($client);

        self::expectException(UnexpectedClientResponseException::class);

        $demandClient->fetchAllInventory(
            new AccountId('0001-00000004-DBEB'),
            'https://example.com',
            'https://app.example.com/inventory',
            false,
        );
    }

    public function testFetchAllInventoryWhileEmptyBody(): void
    {
        $client = self::createMock(Client::class);
        $client->expects(self::once())
            ->method('get')
            ->willReturn(new GuzzleResponse(body: ''));
        /** @var Client $client */
        $demandClient = $this->createGuzzleDemandClient($client);

        self::expectException(EmptyInventoryException::class);

        $demandClient->fetchAllInventory(
            new AccountId('0001-00000004-DBEB'),
            'https://example.com',
            'https://app.example.com/inventory',
            false,
        );
    }

    public function testFetchPaymentDetails(): void
    {
        $client = self::createMock(Client::class);
        $client->expects(self::once())
            ->method('get')
            ->with(
                self::callback(
                    fn(string $path) => 1 === preg_match(
                        '~/payment-details'
                            . '/0001:00000001:0001'
                            . '/0001-00000005-CBCA'
                            . '/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00'
                            . '/\?limit=1&offset=0~',
                        $path,
                    ),
                ),
                self::callback(fn() => true)
            )
            ->willReturn(new GuzzleResponse(body: <<<JSON
[
    {
        "case_id": "10000000000000000000000000000000",
        "event_value": 123000000000
    }
]
JSON
            ));
        /** @var Client $client */
        $demandClient = $this->createGuzzleDemandClient($client);

        $paymentDetails = $demandClient->fetchPaymentDetails('https://example.com', '0001:00000001:0001', 1, 0);

        self::assertCount(1, $paymentDetails);
        self::assertEquals('10000000000000000000000000000000', $paymentDetails[0]['case_id']);
        self::assertEquals(123_000_000_000, $paymentDetails[0]['event_value']);
    }

    public function testFetchPaymentDetailsWhileInvalidResponseHttpStatusCode(): void
    {
        $client = self::createMock(Client::class);
        $client->expects(self::once())
            ->method('get')
            ->willReturn(new GuzzleResponse(500));
        /** @var Client $client */
        $demandClient = $this->createGuzzleDemandClient($client);

        self::expectException(UnexpectedClientResponseException::class);

        $demandClient->fetchPaymentDetails('https://example.com', '0001:00000001:0001', 1, 0);
    }

    public function testFetchPaymentDetailsWhileClientException(): void
    {
        $client = self::createMock(Client::class);
        $client->expects(self::once())
            ->method('get')
            ->willThrowException(
                new RequestException('test exception', new Request('GET', 'https://app.example.com/payment-details'))
            );
        /** @var Client $client */
        $demandClient = $this->createGuzzleDemandClient($client);

        self::expectException(UnexpectedClientResponseException::class);

        $demandClient->fetchPaymentDetails('https://example.com', '0001:00000001:0001', 1, 0);
    }

    private function createGuzzleDemandClient(Client $client): GuzzleDemandClient
    {
        $classifierExternalRepository = self::createMock(ClassifierExternalRepository::class);
        $classifierExternalSignatureVerifier = self::createMock(ClassifierExternalSignatureVerifier::class);
        $signatureVerifier = self::createMock(SignatureVerifier::class);
        $adsAuthenticator = self::createMock(AdsAuthenticator::class);

        return new GuzzleDemandClient(
            $classifierExternalRepository,
            $classifierExternalSignatureVerifier,
            $client,
            $signatureVerifier,
            $adsAuthenticator,
        );
    }

    private static function getInfoJson(): string
    {
        return file_get_contents(base_path('tests/mock/info.json'));
    }

    private function getClientMock(ResponseInterface $responseMock): Client
    {
        $client = self::getMockBuilder(Client::class)->getMock();
        $client->expects(self::once())
            ->method('get')
            ->willReturn($responseMock);
        /** @var Client $client */
        return $client;
    }

    private function getInventoryResponse(): string
    {
        return <<<JSON
[
    {
        "id": "12345678901234567890123456789012",
        "landing_url": "https://landing.example.com",
        "date_start": "2023-01-01T00:00:00+00:00",
        "date_end": null,
        "created_at": "2022-01-01T00:00:00+00:00",
        "updated_at": "2022-01-01T00:00:00+00:00",
        "medium": "web",
        "vendor": null,
        "max_cpc": 0,
        "max_cpm": 300000000000,
        "budget": 371250000000,
        "banners": [
            {
                "id": "0123456789abcdef0123456789abcdef",
                "size": "300x250",
                "type": "image",
                "mime": "image/png",
                "checksum": "fdf53fcb69012345678b6bbf69c33b348ebc6e85",
                "serve_url": "https://example.com/serve/x0123456789abcdef0123456789abcdef.doc?v=f5f5",
                "click_url": "https://example.com/click/0123456789abcdef0123456789abcdef",
                "view_url": "https://example.com/view/0123456789abcdef0123456789abcdef"
            }
        ],
        "targeting_requires": [],
        "targeting_excludes": []
    }
]
JSON;
    }
}
