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

namespace Adshares\Adserver\Tests\Client;

use Adshares\Adserver\Client\GuzzleDemandClient;
use Adshares\Adserver\Repository\Common\ClassifierExternalRepository;
use Adshares\Adserver\Services\Common\ClassifierExternalSignatureVerifier;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\AdsAuthenticator;
use Adshares\Common\Application\Service\SignatureVerifier;
use Adshares\Common\Domain\ValueObject\Url;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
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

        self::expectException(RuntimeException::class);
        $demandClient->fetchInfo(new Url('https://example.com/info.json'));
    }

    public function invalidContentProvider(): array
    {
        return [
            'empty' => [''],
            'malformed' => ['{"mo'],
        ];
    }

    public function testFetchInfoExceptionDueToMissingFields(): void
    {
        $responseMock = self::createMock(ResponseInterface::class);
        $responseMock->method('getStatusCode')->willReturn(Response::HTTP_OK);
        $responseMock->method('getBody')->willReturn('[]');
        /** @var ResponseInterface $responseMock */
        $client = $this->getClientMock($responseMock);
        $demandClient = $this->createGuzzleDemandClient($client);

        self::expectException(UnexpectedClientResponseException::class);
        $demandClient->fetchInfo(new Url('https://example.com/info.json'));
    }

    public function testFetchInfoExceptionDueToClientException(): void
    {
        $client = self::getMockBuilder(Client::class)->getMock();
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
            15,
        );
    }

    private static function getInfoJson(): string
    {
        return file_get_contents('tests/mock/info.json');
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
}
