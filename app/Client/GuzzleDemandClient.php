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

namespace Adshares\Adserver\Client;

use Adshares\Adserver\Client\Mapper\AbstractFilterMapper;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Repository\Common\ClassifierExternalRepository;
use Adshares\Adserver\Services\Common\ClassifierExternalSignatureVerifier;
use Adshares\Adserver\Services\Supply\SiteFilteringUpdater;
use Adshares\Adserver\Utilities\AdsAuthenticator;
use Adshares\Adserver\ViewModel\MediumName;
use Adshares\Adserver\ViewModel\MetaverseVendor;
use Adshares\Common\Application\Service\SignatureVerifier;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Common\UrlInterface;
use Adshares\Supply\Application\Dto\Info;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Application\Service\Exception\EmptyInventoryException;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Adshares\Supply\Domain\Factory\CampaignFactory;
use Adshares\Supply\Domain\Model\CampaignCollection;
use DateTime;
use DateTimeInterface;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\HttpFoundation\Response;

final class GuzzleDemandClient implements DemandClient
{
    private const VERSION = '0.1';
    private const DEFAULT_VENDOR = null;
    private const PAYMENT_DETAILS_ENDPOINT = '/payment-details/{transactionId}/{accountAddress}/{date}/{signature}'
    . '?limit={limit}&offset={offset}';

    public function __construct(
        private readonly ClassifierExternalRepository $classifierRepository,
        private readonly ClassifierExternalSignatureVerifier $classifierExternalSignatureVerifier,
        private readonly Client $client,
        private readonly SignatureVerifier $signatureVerifier,
        private readonly AdsAuthenticator $adsAuthenticator,
    ) {
    }

    public function fetchAllInventory(
        AccountId $sourceAddress,
        string $sourceHost,
        string $inventoryUrl,
        bool $isAdsTxtRequiredBySourceHost,
    ): CampaignCollection {
        try {
            $response = $this->client->get($inventoryUrl, $this->requestParameters());
        } catch (ClientExceptionInterface $exception) {
            throw new UnexpectedClientResponseException(
                sprintf('Could not connect to %s host (%s).', $sourceHost, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }

        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();

        $this->validateResponse($statusCode, $body);

        $address = $sourceAddress->toString();
        $campaigns = $this->createDecodedResponseFromBody($body);
        $campaignDemandIdsToSupplyIds = $this->getCampaignDemandIdsToSupplyIds($campaigns, $address);
        $bannerDemandIdsToSupplyIds = $this->getBannerDemandIdsToSupplyIds($campaigns, $address);

        $rejectCampaignsRequiringAdsTxt = $isAdsTxtRequiredBySourceHost && !config('app.ads_txt_check_supply_enabled');
        $campaignsCollection = new CampaignCollection();
        foreach ($campaigns as $data) {
            try {
                $campaign =
                    CampaignFactory::createFromArray(
                        $this->processData(
                            $data,
                            $sourceHost,
                            $address,
                            $campaignDemandIdsToSupplyIds,
                            $bannerDemandIdsToSupplyIds
                        )
                    );
                if ($rejectCampaignsRequiringAdsTxt && MediumName::Web->value === $campaign->getMedium()) {
                    Log::info(sprintf('[Inventory Importer] Reject campaign %s', $campaign->getDemandCampaignId()));
                    continue;
                }
                $campaignsCollection->add($campaign);
            } catch (RuntimeException $exception) {
                Log::info(sprintf('[Inventory Importer] %s', $exception->getMessage()));
            }
        }

        return $campaignsCollection;
    }

    public function fetchPaymentDetails(string $host, string $transactionId, int $limit, int $offset): array
    {
        $privateKey = Crypt::decryptString(config('app.adshares_secret'));
        $accountAddress = config('app.adshares_address');
        $date = new DateTime();
        $signature = $this->signatureVerifier->createFromTransactionId(
            $privateKey,
            $transactionId,
            $accountAddress,
            $date
        );

        $dateFormatted = $date->format(DateTimeInterface::ATOM);

        $endpoint = str_replace(
            [
                '{transactionId}',
                '{accountAddress}',
                '{date}',
                '{signature}',
                '{limit}',
                '{offset}',
            ],
            [
                $transactionId,
                $accountAddress,
                $dateFormatted,
                $signature,
                $limit,
                $offset,
            ],
            self::PAYMENT_DETAILS_ENDPOINT
        );

        try {
            $response = $this->client->get($endpoint, $this->requestParameters($host));
        } catch (ClientExceptionInterface $exception) {
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
        try {
            $response = $this->client->get(
                (string)$infoUrl,
                $this->requestParameters()
            );
        } catch (ClientExceptionInterface $exception) {
            throw new UnexpectedClientResponseException(
                sprintf('Could not connect to %s (%s).', $infoUrl->toString(), $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode !== Response::HTTP_OK) {
            throw new UnexpectedClientResponseException(sprintf('Unexpected response code `%s`', $statusCode));
        }

        $body = (string)$response->getBody();
        $data = $this->createDecodedResponseFromBody($body);
        $this->validateFetchInfoResponse($data);

        return Info::fromArray($data);
    }

    private function requestParameters(?string $baseUrl = null): array
    {
        $params = [
            RequestOptions::HEADERS => [
                'Content-Type' => 'application/json',
                'Cache-Control' => 'no-cache',
                'Authorization' => $this->adsAuthenticator->getHeader(
                    config('app.adshares_address'),
                    Crypt::decryptString(config('app.adshares_secret'))
                ),
            ],
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
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new UnexpectedClientResponseException('Invalid json data.');
        }
        return $decoded;
    }

    private function processData(
        array $data,
        string $sourceHost,
        string $sourceAddress,
        array $campaignDemandIdsToSupplyIds,
        array $bannerDemandIdsToSupplyIds
    ): array {
        $data['demand_id'] = Uuid::fromString($data['id']);
        $data['date_start'] = DateTime::createFromFormat(DateTimeInterface::ATOM, $data['date_start']);
        $data['date_end'] = $data['date_end'] ? DateTime::createFromFormat(
            DateTimeInterface::ATOM,
            $data['date_end']
        ) : null;

        $data['source_campaign'] = [
            'host' => $sourceHost,
            'address' => $sourceAddress,
            'version' => self::VERSION,
            'created_at' => DateTime::createFromFormat(DateTimeInterface::ATOM, $data['created_at']),
            'updated_at' => DateTime::createFromFormat(DateTimeInterface::ATOM, $data['updated_at']),
        ];

        $classifiersRequired = $this->classifierRepository->fetchRequiredClassifiersNames();
        $banners = [];
        foreach ((array)$data['banners'] as $banner) {
            $banner['demand_banner_id'] = Uuid::fromString($banner['id']);

            if (array_key_exists($banner['id'], $bannerDemandIdsToSupplyIds)) {
                $banner['id'] = Uuid::fromString($bannerDemandIdsToSupplyIds[$banner['id']]);
            } else {
                unset($banner['id']);
            }

            $banner['classification'] = $this->validateAndMapClassification($banner);
            if ($this->missingRequiredClassifier($classifiersRequired, $banner['classification'])) {
                continue;
            }

            $banners[] = $banner;
        }

        $data['created_at'] = new DateTime();
        $data['updated_at'] = new DateTime();
        $data['budget'] = (int)$data['budget'];
        $data['max_cpc'] = (int)$data['max_cpc'];
        $data['max_cpm'] = (int)$data['max_cpm'];
        $data['banners'] = $banners;
        [$data['medium'], $data['vendor']] = self::extractMediumAndVendor($data);

        if (array_key_exists($data['id'], $campaignDemandIdsToSupplyIds)) {
            $data['id'] = Uuid::fromString($campaignDemandIdsToSupplyIds[$data['id']]);
        } else {
            unset($data['id']);
        }

        return $data;
    }

    private static function extractMediumAndVendor(array $data): array
    {
        if ($data['medium'] ?? false) {
            return [$data['medium'], $data['vendor'] ?? self::DEFAULT_VENDOR];
        }

        if ($data['targeting_requires']['site']['domain'] ?? false) {
            $domains = $data['targeting_requires']['site']['domain'];

            foreach (MetaverseVendor::cases() as $metaverseVendor) {
                $vendorDomain = $metaverseVendor->baseDomain();
                $matchesCount = 0;
                foreach ($domains as $domain) {
                    if (!str_ends_with($domain, $vendorDomain)) {
                        break;
                    }
                    ++$matchesCount;
                }
                if (count($domains) === $matchesCount) {
                    return [MediumName::Metaverse->value, $metaverseVendor->value];
                }
            }
        }

        return [MediumName::Web->value, self::DEFAULT_VENDOR];
    }

    public function validateFetchInfoResponse(array $data): void
    {
        $expectedKeys = [
            'capabilities',
            'inventoryUrl',
            'module',
            'name',
            'panelUrl',
            'privacyUrl',
            'serverUrl',
            'termsUrl',
            'version',
        ];

        foreach ($expectedKeys as $key) {
            if (!isset($data[$key])) {
                Log::debug(__METHOD__ . ' Invalid info.json: ' . json_encode($data));
                throw new UnexpectedClientResponseException(sprintf('Field `%s` is required.', $key));
            }
        }
    }

    private function getCampaignDemandIdsToSupplyIds(array $campaigns, string $sourceAddress): array
    {
        $campaignIds = [];
        foreach ($campaigns as $campaign) {
            $campaignIds[] = $campaign['id'];
        }

        return NetworkCampaign::findSupplyIdsByDemandIdsAndAddress($campaignIds, $sourceAddress);
    }

    private function getBannerDemandIdsToSupplyIds(array $campaigns, string $sourceAddress): array
    {
        $bannerDemandIds = [];
        foreach ($campaigns as $campaign) {
            foreach ((array)$campaign['banners'] as $banner) {
                $bannerDemandIds[] = $banner['id'];
            }
        }

        return NetworkBanner::findSupplyIdsByDemandIds($bannerDemandIds, $sourceAddress);
    }

    private function validateAndMapClassification(array $banner): array
    {
        $classification = $banner['classification'] ?? [];
        $checksum = $banner['checksum'] ?? '';
        $invalidClassifiers = [];
        foreach ($classification as $classifier => $classificationItem) {
            if (
                !isset($classificationItem['signature']) || !isset($classificationItem['signed_at'])
                || !$this->classifierExternalSignatureVerifier->isSignatureValid(
                    (string)$classifier,
                    $classificationItem['signature'],
                    $checksum,
                    DateTime::createFromFormat(
                        DateTimeInterface::ATOM,
                        $classificationItem['signed_at']
                    )->getTimestamp(),
                    $classificationItem['keywords'] ?? []
                )
            ) {
                $invalidClassifiers[] = $classifier;
            }
        }
        foreach ($invalidClassifiers as $invalidClassifier) {
            unset($classification[$invalidClassifier]);
        }

        $flatClassification = [];
        foreach ($classification as $classifier => $classificationItem) {
            $keywords = $classificationItem['keywords'] ?? [];
            $keywords[SiteFilteringUpdater::KEYWORD_CLASSIFIED] =
                SiteFilteringUpdater::KEYWORD_CLASSIFIED_VALUE;

            $flatClassification[$classifier] = AbstractFilterMapper::generateNestedStructure(
                $keywords
            );
        }

        return $flatClassification;
    }

    private function missingRequiredClassifier(array $classifiersRequired, array $classification): bool
    {
        if (empty($classifiersRequired)) {
            return false;
        }

        $classifiers = array_keys($classification);

        return empty(array_intersect($classifiersRequired, $classifiers));
    }
}
