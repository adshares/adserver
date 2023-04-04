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

use Adshares\Adserver\Client\Mapper\AdSelect\CampaignMapper;
use Adshares\Adserver\Client\Mapper\AdSelect\CaseClickMapper;
use Adshares\Adserver\Client\Mapper\AdSelect\CaseMapper;
use Adshares\Adserver\Client\Mapper\AdSelect\CasePaymentMapper;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\ServeDomain;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Adserver\Utilities\DomainReader;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Exception\RuntimeException as DomainRuntimeException;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Adshares\Supply\Application\Dto\FoundBanners;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Service\AdSelect;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\Model\CampaignCollection;
use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\Utils as GuzzleUtils;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GuzzleAdSelectClient implements AdSelect
{
    private const URI_CASE_EXPORT = '/api/v1/cases';
    private const URI_CASE_LAST_EXPORTED_ID = '/api/v1/cases/last';
    private const URI_CASE_CLICK_EXPORT = '/api/v1/clicks';
    private const URI_CASE_CLICK_LAST_EXPORTED_ID = '/api/v1/clicks/last';
    private const URI_CASE_PAYMENT_EXPORT = '/api/v1/payments';
    private const URI_CASE_PAYMENT_LAST_EXPORTED_ID = '/api/v1/payments/last';
    private const URI_FIND_BANNERS = '/api/v1/find';
    private const URI_INVENTORY = '/api/v1/campaigns';

    public function __construct(private readonly Client $client)
    {
    }

    public function exportInventory(CampaignCollection $campaigns): void
    {
        $configurationRepository = resolve(ConfigurationRepository::class);
        $mapped = [];

        /** @var Campaign $campaign */
        foreach ($campaigns as $campaign) {
            $mapped[] = CampaignMapper::map(
                $configurationRepository->fetchMedium($campaign->getMedium(), $campaign->getVendor()),
                $campaign
            );
        }
        try {
            $this->client->post(
                self::URI_INVENTORY,
                [
                    RequestOptions::JSON => ['campaigns' => $mapped],
                ]
            );
        } catch (RequestException $exception) {
            throw new UnexpectedClientResponseException(
                sprintf(
                    '[ADSELECT] Export inventory to %s failed (%s).',
                    $this->client->getConfig()['base_uri'],
                    $exception->getMessage()
                ),
                $exception->getCode(),
                $exception
            );
        }
    }

    public function deleteFromInventory(CampaignCollection $campaigns): void
    {
        $mapped = [];

        /** @var Campaign $campaign */
        foreach ($campaigns as $campaign) {
            $mapped[] = $campaign->getId();
        }

        try {
            $this->client->delete(
                self::URI_INVENTORY,
                [
                    RequestOptions::JSON => ['campaigns' => $mapped],
                ]
            );
        } catch (RequestException $exception) {
            throw new UnexpectedClientResponseException(
                sprintf(
                    '[ADSELECT] Delete campaigns (%s) from %s failed (%s).',
                    json_encode($mapped),
                    $this->client->getConfig()['base_uri'],
                    $exception->getMessage()
                ),
                $exception->getCode(),
                $exception
            );
        }
    }

    public function findBanners(array $zones, ImpressionContext $context, string $impressionId): FoundBanners
    {
        $zoneInputByUuid = [];
        $zoneIds = [];
        foreach ($zones as $zone) {
            $zoneId = $zone['placementId'] ?? (string)$zone['zone'];// Key 'zone' is for legacy search
            $zoneInputByUuid[$zoneId] = $zone;
            $zoneIds[] = strtolower($zoneId);
        }

        $zoneMap = [];
        $sitesMap = [];

        $zoneList = Zone::findByPublicIds($zoneIds);
        /** @var Zone $zone */
        for ($i = 0; $i < count($zoneList); $i++) {
            $zone = $zoneList[$i];
            $siteId = $zone->site_id;

            if (!array_key_exists($siteId, $sitesMap)) {
                $site = $zone->site;

                $isActive = null !== $site && $site->status === Site::STATUS_ACTIVE && null !== ($user = $site->user);

                if ($isActive) {
                    $sitesMap[$siteId] = [
                        'active'       => true,
                        'domain'       => $site->domain,
                        'filters'      => [
                            'require' => $site->site_requires ?: [],
                            'exclude' => $site->site_excludes ?: [],
                        ],
                        'publisher_id' => $user->uuid,
                        'uuid'         => $site->uuid,
                        'medium'       => $site->medium,
                    ];
                    if (null !== $site->vendor) {
                        $sitesMap[$siteId]['vendor'] = $site->vendor;
                    }
                    if (isset($zones[$i]['options']['banner_type'])) {
                        $sitesMap[$siteId]['filters']['require']['type'] = (array)$zones[$i]['options']['banner_type'];
                    }
                    if (isset($zones[$i]['options']['banner_mime'])) {
                        $sitesMap[$siteId]['filters']['require']['mime'] = (array)$zones[$i]['options']['banner_mime'];
                    }
                    if (isset($zones[$i]['options']['exclude'])) {
                        $sitesMap[$siteId]['filters']['exclude'] = array_merge_recursive(
                            $sitesMap[$siteId]['filters']['exclude'],
                            $zones[$i]['options']['exclude'],
                        );
                    }
                    // always include active pop zones
                    foreach ($site->zones as $popupZone) {
                        if (!in_array($popupZone->uuid, $zoneIds)) {
                            if ($popupZone->type == 'pop' && $popupZone->status == Zone::STATUS_ACTIVE) {
                                $zoneIds[] = $popupZone->uuid;
                                $zoneList[] = $popupZone;
                            }
                        }
                    }
                } else {
                    $sitesMap[$siteId] = [
                        'active' => false,
                    ];
                }
            }

            if (
                $sitesMap[$siteId]['active'] && (
                !config('app.check_zone_domain')
                || DomainReader::checkDomain($context->url(), $sitesMap[$siteId]['domain'])
                )
            ) {
                $zoneMap[$zone->uuid] = $zone;
            }
        }

        $zoneCollection = new Collection();
        foreach ($zoneIds as $i => $id) {
            $requestId = $zoneInputByUuid[$id]['id'] ?? $i;
            $zoneCollection[$requestId] = $zoneMap[$id] ?? null;
        }

        $existingZones = $zoneCollection->reject(
            static function ($zone) {
                return $zone === null;
            }
        );

        if ($existingZones->isEmpty()) {
            $items = [];
        } else {
            $zoneInput = [];
            foreach ($existingZones as $key => $zone) {
                $zoneInput[$key] = $zoneInputByUuid[$zone->uuid] ?? [];
            }
            try {
                $result = $this->client->post(
                    self::URI_FIND_BANNERS,
                    [
                        RequestOptions::JSON => $context->adSelectRequestParams($existingZones, $zoneInput, $sitesMap),
                    ]
                );
            } catch (Throwable $exception) {
                throw new UnexpectedClientResponseException(
                    sprintf(
                        '[ADSELECT] Find banners (%s) from %s failed (%s).',
                        json_encode($existingZones),
                        $this->client->getConfig()['base_uri'],
                        $exception->getMessage()
                    ),
                    $exception->getCode(),
                    $exception
                );
            }

            $body = (string)$result->getBody();
            try {
                $items = GuzzleUtils::jsonDecode($body, true);
            } catch (InvalidArgumentException) {
                throw new DomainRuntimeException(sprintf('[ADSELECT] Find Banners. Invalid json data (%s).', $body));
            }
            Log::debug(
                sprintf(
                    '%s:%s %s',
                    __METHOD__,
                    __LINE__,
                    $body
                )
            );
        }

        $bannerIds = [];
        foreach ($zoneCollection as $requestId => $zone) {
            if (isset($existingZones[$requestId]) && isset($items[$requestId])) {
                $bannerIds[$requestId] = $items[$requestId];
            } else {
                $bannerIds[$requestId] = [null];
            }
        }

        $banners = iterator_to_array($this->fetchInOrderOfAppearance($bannerIds, $zoneCollection, $impressionId));

        return new FoundBanners($banners);
    }

    private function fetchInOrderOfAppearance(
        array $params,
        Collection $zoneCollection,
        string $impressionId,
    ): Generator {
        /** @var LicenseReader $licenseReader */
        $licenseReader = resolve(LicenseReader::class);
        $infoBox = $licenseReader->getInfoBox();

        foreach ($params as $requestId => $bannerIds) {
            foreach ($bannerIds as $item) {
                $bannerId = $item['banner_id'] ?? null;
                $banner = $bannerId ? NetworkBanner::fetchByPublicId((string)$bannerId) : null;

                if (null === $banner) {
                    if ($bannerId) {
                        Log::warning(sprintf('Banner %s not found.', $bannerId));
                    }

                    yield null;
                } else {
                    $zone = $zoneCollection[$requestId];
                    $campaign = $banner->campaign;
                    $data = [
                        'id'            => $bannerId,
                        'demand_id'     => $banner->demand_banner_id,
                        'publisher_id'  => $zone->site->user->uuid,
                        'zone_id'       => $zone->uuid,
                        'pay_from'      => $campaign->source_address,
                        'pay_to'        => AdsUtils::normalizeAddress(config('app.adshares_address')),
                        'type'          => $banner->type,
                        'size'          => $banner->size,
                        'serve_url'     => $banner->serve_url,
                        'creative_sha1' => $banner->checksum,
                        'click_url'     =>  ServeDomain::changeUrlHost(SecureUrl::change(
                            route(
                                'log-network-click',
                                [
                                    'id' => $banner->uuid,
                                    'iid' => $impressionId,
                                    'r'  => Utils::urlSafeBase64Encode($banner->click_url),
                                    'zid' => $zone->uuid,
                                ]
                            )
                        )),
                        'view_url'      => ServeDomain::changeUrlHost(SecureUrl::change(
                            route(
                                'log-network-view',
                                [
                                    'id' => $banner->uuid,
                                    'iid' => $impressionId,
                                    'r'  => Utils::urlSafeBase64Encode($banner->view_url),
                                    'zid' => $zone->uuid,
                                ]
                            )
                        )),
                        'info_box'      => $infoBox,
                        'rpm'           => $item['rpm'],
                        'request_id'    => (string)$requestId,
                    ];
                    yield $data;
                }
            }
        }
    }

    public function exportCases(Collection $cases): void
    {
        $mapped = [];
        foreach ($cases as $case) {
            $mapped[] = CaseMapper::map($case);
        }

        $options = [
            RequestOptions::JSON => ['cases' => $mapped],
        ];

        $this->export(self::URI_CASE_EXPORT, $options);
    }

    public function exportCaseClicks(Collection $caseClicks): void
    {
        $mapped = [];
        foreach ($caseClicks as $caseClick) {
            $mapped[] = CaseClickMapper::map($caseClick);
        }

        $options = [
            RequestOptions::JSON => ['clicks' => $mapped],
        ];

        $this->export(self::URI_CASE_CLICK_EXPORT, $options);
    }

    public function exportCasePayments(Collection $casePayments): void
    {
        $mapped = [];
        foreach ($casePayments as $casePayment) {
            $mapped[] = CasePaymentMapper::map($casePayment);
        }

        $options = [
            RequestOptions::JSON => ['payments' => $mapped],
        ];

        $this->export(self::URI_CASE_PAYMENT_EXPORT, $options);
    }

    public function export(string $uri, array $options): void
    {
        try {
            $this->client->post($uri, $options);
        } catch (RequestException $exception) {
            throw new UnexpectedClientResponseException(
                sprintf(
                    '[ADSELECT] Export to (%s) (%s) failed (%s).',
                    $this->client->getConfig()['base_uri'],
                    $uri,
                    $exception->getMessage()
                ),
                $exception->getCode(),
                $exception
            );
        }
    }

    public function getLastExportedCaseId(): int
    {
        return $this->getLastExportedId(self::URI_CASE_LAST_EXPORTED_ID);
    }

    public function getLastExportedCaseClickId(): int
    {
        return $this->getLastExportedId(self::URI_CASE_CLICK_LAST_EXPORTED_ID);
    }

    public function getLastExportedCasePaymentId(): int
    {
        return $this->getLastExportedId(self::URI_CASE_PAYMENT_LAST_EXPORTED_ID);
    }

    private function getLastExportedId(string $uri): int
    {
        try {
            $response = $this->client->get($uri);
        } catch (RequestException $exception) {
            if ($exception->getCode() === Response::HTTP_NOT_FOUND) {
                return 0;
            }

            throw new UnexpectedClientResponseException(
                sprintf(
                    '[ADSELECT] Fetch last id (%s) from (%s) failed (%s).',
                    $uri,
                    $this->client->getConfig()['base_uri'],
                    $exception->getMessage()
                ),
                $exception->getCode(),
                $exception
            );
        }

        $body = (string)$response->getBody();
        try {
            $item = GuzzleUtils::jsonDecode($body, true);
        } catch (InvalidArgumentException) {
            throw new DomainRuntimeException(
                sprintf(
                    '[ADSELECT] Fetch last id (%s). Invalid json data (%s).',
                    $uri,
                    $body
                )
            );
        }

        if (!isset($item['id'])) {
            throw new UnexpectedClientResponseException(
                sprintf(
                    '[ADSELECT] Could not fetch last id (%s) from (%s). Id is required, given (%s).',
                    $uri,
                    $this->client->getConfig()['base_uri'],
                    $body
                )
            );
        }

        return (int)$item['id'];
    }
}
