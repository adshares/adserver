<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

declare(strict_types = 1);

namespace Adshares\Adserver\Client;

use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Common\Application\Service\SignatureVerifier;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Common\Exception\RuntimeException as DomainRuntimeException;
use Adshares\Common\UrlInterface;
use Adshares\Supply\Application\Dto\Info;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Application\Service\Exception\EmptyInventoryException;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Adshares\Supply\Domain\Factory\CampaignFactory;
use Adshares\Supply\Domain\Model\CampaignCollection;
use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use function GuzzleHttp\json_decode;
use function json_encode;

final class GuzzleDemandClient implements DemandClient
{
    private const VERSION = '0.1';

    private const PAYMENT_DETAILS_ENDPOINT = '/payment-details/{transactionId}/{accountAddress}/{date}/{signature}';

    /** @var SignatureVerifier */
    private $signatureVerifier;

    /** @var int */
    private $timeout;

    public function __construct(SignatureVerifier $signatureVerifier, int $timeout)
    {
        $this->signatureVerifier = $signatureVerifier;
        $this->timeout = $timeout;
    }

    public function fetchAllInventory(string $sourceHost, string $inventoryUrl): CampaignCollection
    {
        $client = new Client($this->requestParameters());

        try {
            $response = $client->get($inventoryUrl);
        } catch (RequestException $exception) {
            throw new UnexpectedClientResponseException(
                sprintf('Could not connect to %s host (%s).', $sourceHost, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }

        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();

        $this->validateResponse($statusCode, $body);

        $campaigns = $this->createDecodedResponseFromBody($body);
        $campaignDemandIdsToSupplyIds = $this->getCampaignDemandIdsToSupplyIds($campaigns);
        $bannerDemandIdsToSupplyIds = $this->getBannerDemandIdsToSupplyIds($campaigns);

        $campaignsCollection = new CampaignCollection();
        foreach ($campaigns as $data) {
            try {
                $data =
                    CampaignFactory::createFromArray(
                        $this->processData(
                            $data,
                            $sourceHost,
                            $campaignDemandIdsToSupplyIds,
                            $bannerDemandIdsToSupplyIds
                        )
                    );
                $campaignsCollection->add($data);
            } catch (RuntimeException $exception) {
                Log::info(sprintf('[Inventory Importer] %s', $exception->getMessage()));
            }
        }

        return $campaignsCollection;
    }

    public function fetchPaymentDetails(string $host, string $transactionId): array
    {
        $client = new Client($this->requestParameters($host));

        $privateKey = (string)config('app.adshares_secret');
        $accountAddress = (string)config('app.adshares_address');
        $date = new DateTime();
        $signature = $this->signatureVerifier->create($privateKey, $transactionId, $accountAddress, $date);

        $dateFormatted = $date->format(DateTime::ATOM);

        $endpoint = str_replace(
            [
                '{transactionId}',
                '{accountAddress}',
                '{date}',
                '{signature}',
            ],
            [
                $transactionId,
                $accountAddress,
                $dateFormatted,
                $signature,
            ],
            self::PAYMENT_DETAILS_ENDPOINT
        );

        try {
            $response = $client->get($endpoint);
        } catch (ClientException $exception) {
            throw new UnexpectedClientResponseException(
                sprintf('Transaction not found: %s.', $exception->getMessage()),
                $exception->getCode()
            );
        }

        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();
        $this->validateResponse($statusCode, $body);

        return $this->createDecodedResponseFromBody($body);
    }

    public function fetchInfo(UrlInterface $infoUrl): Info
    {
        $client = new Client($this->requestParameters());

        try {
            $response = $client->get((string)$infoUrl);
        } catch (RequestException $exception) {
            throw new UnexpectedClientResponseException(
                sprintf('Could not connect to %s (%s).', (string)$infoUrl, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }

        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();

        $this->validateResponse($statusCode, $body);
        $data = $this->createDecodedResponseFromBody($body);

        $this->validateFetchInfoResponse($data);

        return Info::fromArray($data);
    }

    private function requestParameters(?string $baseUrl = null): array
    {
        $params = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-cache',
            ],
            'timeout' => $this->timeout,
        ];

        if ($baseUrl) {
            $params['base_uri'] = $baseUrl;
        }

        return $params;
    }

    private function validateResponse(int $statusCode, string $body): void
    {
        if ($statusCode !== Response::HTTP_OK) {
            throw new UnexpectedClientResponseException(sprintf('Unexpected response code `%s`.', $statusCode));
        }

        if (empty($body)) {
            throw new EmptyInventoryException('Empty list');
        }
    }

    private function createDecodedResponseFromBody(string $body): array
    {
        try {
            $decoded = json_decode($body, true);
        } catch (InvalidArgumentException $exception) {
            throw new DomainRuntimeException('Invalid json data.');
        }

        return $decoded;
    }

    private function processData(
        array $data,
        string $sourceHost,
        array $campaignDemandIdsToSupplyIds,
        array $bannerDemandIdsToSupplyIds
    ): array {
        $data['demand_id'] = Uuid::fromString($data['id']);
        $data['date_start'] = DateTime::createFromFormat(DateTime::ATOM, $data['date_start']);
        $data['date_end'] = $data['date_end'] ? DateTime::createFromFormat(DateTime::ATOM, $data['date_end']) : null;

        $data['source_campaign'] = [
            'host' => $sourceHost,
            'address' => $data['address'],
            'version' => self::VERSION,
            'created_at' => DateTime::createFromFormat(DateTime::ATOM, $data['created_at']),
            'updated_at' => DateTime::createFromFormat(DateTime::ATOM, $data['updated_at']),
        ];

        $banners = [];
        foreach ((array)$data['banners'] as $banner) {
            $banner['demand_banner_id'] = Uuid::fromString($banner['id']);

            if ($bannerDemandIdsToSupplyIds[$banner['id']]) {
                $banner['id'] = Uuid::fromString($bannerDemandIdsToSupplyIds[$banner['id']]);
            } else {
                unset($banner['id']);
            }

            $banners[] = $banner;
        }

        $data['created_at'] = new DateTime();
        $data['updated_at'] = new DateTime();
        $data['budget'] = (int)$data['budget'];
        $data['max_cpc'] = (int)$data['max_cpc'];
        $data['max_cpm'] = (int)$data['max_cpm'];
        $data['banners'] = $banners;

        if ($campaignDemandIdsToSupplyIds[$data['id']]) {
            $data['id'] = Uuid::fromString($campaignDemandIdsToSupplyIds[$data['id']]);
        } else {
            unset($data['id']);
        }

        return $data;
    }

    public function validateFetchInfoResponse(array $data): void
    {
        $expectedKeys = [
            'name',
            'serverUrl',
            'panelUrl',
            'privacyUrl',
            'termsUrl',
            'inventoryUrl',
        ];

        foreach ($expectedKeys as $key) {
            if (!isset($data[$key])) {
                Log::debug(__METHOD__.' Invalid info.json: '.json_encode($data));

                throw new UnexpectedClientResponseException(sprintf('Field `%s` is required.', $key));
            }
        }

        if (!isset($data['version']) && !isset($data['softwareVersion'])) {
            throw new UnexpectedClientResponseException('Field `version` (deprecated: `softwareVersion`) is required.');
        }

        if (!isset($data['module']) && !isset($data['serviceType'])) {
            throw new UnexpectedClientResponseException('Field `module` (deprecated: `serviceType`) is required.');
        }

        if (!isset($data['capabilities']) && !isset($data['supported'])) {
            throw new UnexpectedClientResponseException('Field `capabilities` (deprecated: `supported`) is required.');
        }
    }

    private function getCampaignDemandIdsToSupplyIds(array $campaigns): array
    {
        $campaignIds = [];
        foreach ($campaigns as $campaign) {
            $campaignIds[] = $campaign['id'];
        }

        return NetworkCampaign::findSupplyIdsByDemandIds($campaignIds);
    }

    private function getBannerDemandIdsToSupplyIds(array $campaigns): array
    {
        $bannerDemandIds = [];
        foreach ($campaigns as $campaign) {
            foreach ((array)$campaign['banners'] as $banner) {
                $bannerDemandIds[] = $banner['id'];
            }
        }
        
        return NetworkBanner::findSupplyIdsByDemandIds($bannerDemandIds);
    }
}
