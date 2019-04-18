<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Client;

use Adshares\Adserver\Client\Mapper\AdSelect\CampaignMapper;
use Adshares\Adserver\Client\Mapper\AdSelect\EventMapper;
use Adshares\Adserver\Client\Mapper\AdSelect\EventPaymentMapper;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\HttpClient\JsonRpc;
use Adshares\Adserver\HttpClient\JsonRpc\Procedure;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Supply\Application\Dto\FoundBanners;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Service\AdSelect;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\Model\CampaignCollection;
use Generator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use function array_map;
use function iterator_to_array;
use function sprintf;
use function strtoupper;

final class JsonRpcAdSelectClient implements AdSelect
{
    private const METHOD_CAMPAIGN_UPDATE = 'campaign_update';

    private const METHOD_CAMPAIGN_DELETE = 'campaign_delete';

    private const METHOD_BANNER_SELECT = 'banner_select';

    private const METHOD_EVENT_UPDATE = 'impression_add';

    private const METHOD_EVENT_PAYMENT_ADD = 'impression_payment_add';

    /** @var JsonRpc */
    private $client;

    public function __construct(JsonRpc $client)
    {
        $this->client = $client;
    }

    public function findBanners(array $zones, ImpressionContext $context): FoundBanners
    {
        $zoneIds = array_map(
            function (array $zone) {
                return $zone['zone'];
            },
            $zones
        );

        $zones = Zone::findByPublicIds($zoneIds);

        if ($zones->count() !== count($zoneIds)) {
            $zones = $this->attachDuplicatedZones($zones, $zoneIds);
        }

        $existingZones = $zones->reject(function ($zone) {
            return $zone === null;
        });

        $procedure = new Procedure(
            self::METHOD_BANNER_SELECT,
            $context->adSelectRequestParams($existingZones)
        );

        $result = $this->client->call($procedure);

        $items = $result->toArray();

        Log::debug(sprintf(
            '%s:%s %s',
            __METHOD__,
            __LINE__,
            $procedure->toJson()
        ));

        $bannerMap = $this->createRequestIdsToBannerMap($items);
        $bannerIds = $this->fixBannerOrdering($existingZones, $bannerMap);

        $banners = iterator_to_array($this->fetchInOrderOfAppearance($bannerIds));

        return new FoundBanners($banners);
    }

    public function exportInventory(Campaign $campaign): void
    {
        $procedure = new Procedure(
            self::METHOD_CAMPAIGN_UPDATE,
            CampaignMapper::map($campaign)
        );

        $this->client->call($procedure)->isTrue();

        Log::debug(sprintf(
            '%s:%s %s',
            __METHOD__,
            __LINE__,
            $procedure->toJson()
        ));
    }

    public function exportEvents(array $eventsInput): void
    {
        $events = [];

        foreach ($eventsInput as $event) {
            $events[] = EventMapper::map($event);
        }

        $procedure = new Procedure(self::METHOD_EVENT_UPDATE, $events);

        $this->client->call($procedure)->isTrue();
    }

    public function exportEventsPayments(array $eventsInput): void
    {
        $events = [];

        foreach ($eventsInput as $event) {
            $events[] = EventPaymentMapper::map($event);
        }

        $procedure = new Procedure(self::METHOD_EVENT_PAYMENT_ADD, $events);

        $this->client->call($procedure)->isTrue();

        Log::debug(sprintf(
            '%s:%s %s',
            __METHOD__,
            __LINE__,
            $procedure->toJson()
        ));
    }

    public function deleteFromInventory(CampaignCollection $campaigns): void
    {
        $mappedCampaigns = [];

        /** @var Campaign $campaign */
        foreach ($campaigns as $campaign) {
            $mappedCampaigns[] = $campaign->getId();
        }

        $procedure = new Procedure(
            self::METHOD_CAMPAIGN_DELETE,
            $mappedCampaigns
        );

        $this->client->call($procedure)->isTrue();
    }

    private function attachDuplicatedZones(Collection $uniqueZones, array $zoneIds): Collection
    {
        $zones = [];
        foreach ($zoneIds as $zonePublicIdPassedFromPublisher) {
            $zones[] = $uniqueZones->filter(
                function (Zone $zone) use ($zonePublicIdPassedFromPublisher) {
                    return strtoupper($zone->uuid) === strtoupper($zonePublicIdPassedFromPublisher);
                }
            )->first();
        }

        return new Collection($zones);
    }

    private function createRequestIdsToBannerMap(array $items): array
    {
        $idMap = [];

        foreach ($items as $item) {
            $requestId = $item['request_id'];
            if ($requestId !== false) {
                $idMap[$requestId] = $item['banner_id'];
            }
        }

        return $idMap;
    }

    private function fixBannerOrdering(Collection $zones, array $bannerMap): array
    {
        $bannerIds = [];

        foreach ($zones as $requestId => $zone) {
            $bannerId = $bannerMap[$requestId] ?? null;

            if ($bannerId === null) {
                Log::warning(sprintf('Zone %s not found (AdSelect `request_id`: %s).', $zone->id, $requestId));
            }

            $bannerIds[$zone->uuid][] = $bannerId;
        }

        return $bannerIds;
    }

    private function fetchInOrderOfAppearance(array $params): Generator
    {
        foreach ($params as $zoneId => $bannerIds) {
            foreach ($bannerIds as $bannerId) {
                $banner = $bannerId ? NetworkBanner::findByUuid($bannerId) : null;

                if (null === $banner) {
                    Log::warning(sprintf('Banner %s not found.', $bannerId));

                    yield null;
                } else {
                    $campaign = $banner->campaign;
                    yield [
                        'id' => $bannerId,
                        'zone_id' => $zoneId,
                        'pay_from' => $campaign->source_address,
                        'pay_to' => AdsUtils::normalizeAddress(config('app.adshares_address')),
                        'serve_url' => $banner->serve_url,
                        'creative_sha1' => $banner->checksum,
                        'click_url' => SecureUrl::change(
                            route(
                                'log-network-click',
                                [
                                    'id' => $banner->uuid,
                                    'r' => Utils::urlSafeBase64Encode($banner->click_url),
                                ]
                            )
                        ),
                        'view_url' => SecureUrl::change(
                            route(
                                'log-network-view',
                                [
                                    'id' => $banner->uuid,
                                    'r' => Utils::urlSafeBase64Encode($banner->view_url),
                                ]
                            )
                        ),
                    ];
                }
            }
        }
    }
}
