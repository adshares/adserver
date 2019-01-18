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
use Adshares\Adserver\Utilities\UrlProtocolRemover;
use Adshares\Supply\Application\Dto\FoundBanners;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Service\AdSelect;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\Model\CampaignCollection;
use Generator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use function array_map;
use function GuzzleHttp\json_encode;
use function iterator_to_array;
use function sprintf;

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

        $params = $context->adSelectRequestParams($zones);
        $result = $this->client->call(
            new Procedure(
                self::METHOD_BANNER_SELECT,
                $params
            )
        );

        $zoneToBannerMap = $this->createZoneToBannerMap($result->toArray());

        $bannerIds = $this->fixBannerOrdering($zones, $zoneToBannerMap);

        $banners = iterator_to_array($this->fetchInOrderOfAppearance($bannerIds));

        Log::debug(sprintf(
            '{"zones":%s,"banners":%s}',
            json_encode($zoneIds),
            json_encode($bannerIds)
        ));

        return new FoundBanners($banners);
    }

    public function exportInventory(Campaign $campaign): void
    {
        $procedure = new Procedure(
            self::METHOD_CAMPAIGN_UPDATE,
            CampaignMapper::map($campaign)
        );

        $this->client->call($procedure);
    }

    public function exportEvents(array $eventsInput): void
    {
        $events = [];

        foreach ($eventsInput as $event) {
            $events[] = EventMapper::map($event);
        }

        $procedure = new Procedure(self::METHOD_EVENT_UPDATE, $events);
        $this->client->call($procedure);
    }

    public function exportEventsPayments(array $eventsInput): void
    {
        $events = [];

        foreach ($eventsInput as $event) {
            $events[] = EventPaymentMapper::map($event);
        }

        $procedure = new Procedure(self::METHOD_EVENT_PAYMENT_ADD, $events);
        $this->client->call($procedure);
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

        $this->client->call($procedure);
    }

    private function createZoneToBannerMap(array $items): array
    {
        $idMap = [];

        foreach ($items as $item) {
            if ($item['request_id']) {
                $idMap[$item['request_id']] = $item['banner_id'];
            }
        }

        return $idMap;
    }

    private function fixBannerOrdering(Collection $zones, array $zoneToBannerMap): array
    {
        $bannerIds = [];

        foreach ($zones as $zone) {
            $bannerId = $zoneToBannerMap[$zone->id] ?? null;

            if ($bannerId === null) {
                Log::warning(sprintf('Zone %s not found.', $zone->id));
            }

            $bannerIds[] = $bannerId;
        }

        return $bannerIds;
    }

    private function fetchInOrderOfAppearance(array $bannerIds): Generator
    {
        foreach ($bannerIds as $bannerId) {
            $banner = $bannerId ? NetworkBanner::findByUuid($bannerId) : null;

            if (null === $banner) {
                Log::warning(sprintf('Banner %s not found.', $bannerId));

                yield null;
            } else {
                $campaign = $banner->campaign;
                yield [
                    'pay_from' => $campaign->source_address,
                    'pay_to' => AdsUtils::normalizeAddress(config('app.adshares_address')),
                    'serve_url' => $banner->serve_url,
                    'creative_sha1' => $banner->checksum,
                    'click_url' => UrlProtocolRemover::remove(route(
                        'log-network-click',
                        [
                            'id' => $banner->uuid,
                            'r' => Utils::urlSafeBase64Encode($banner->click_url),
                        ]
                    )),
                    'view_url' => UrlProtocolRemover::remove(route(
                        'log-network-view',
                        [
                            'id' => $banner->uuid,
                            'r' => Utils::urlSafeBase64Encode($banner->view_url),
                        ]
                    )),
                ];
            }
        }
    }
}
