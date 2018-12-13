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
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\HttpClient\JsonRpc;
use Adshares\Adserver\HttpClient\JsonRpc\Procedure;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Supply\Application\Dto\FoundBanners;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Service\AdSelect;
use Adshares\Supply\Domain\Model\Campaign;
use Generator;
use function array_map;
use function iterator_to_array;

final class JsonRpcAdSelectClient implements AdSelect
{
    private const METHOD_CAMPAIGN_UPDATE = 'campaign_update';

    private const METHOD_BANNER_SELECT = 'banner_select';

    private const METHOD_EVENT_UPDATE = 'impression_add';

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

        $result = $this->client->call(
            new Procedure(
                self::METHOD_BANNER_SELECT,
                $context->adSelectRequestParams(Zone::findByIds($zoneIds))
            )
        );

        $zoneToBannerMap = $this->createZoneToBannerMap($result->toArray());

        $bannerIds = $this->fixBannerOrdering($zoneIds, $zoneToBannerMap);

        $banners = iterator_to_array($this->fetchInOrderOfAppearance($bannerIds));

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

    private function fixBannerOrdering(array $zoneIds, array $zoneToBannerMap): array
    {
        $bannerIds = [];

        foreach ($zoneIds as $id) {
            $bannerIds[] = $zoneToBannerMap[$id] ?? null;
        }

        return $bannerIds;
    }

    private function fetchInOrderOfAppearance(array $bannerIds): Generator
    {
        foreach ($bannerIds as $bannerId) {
            $banner = $bannerId ? NetworkBanner::findByUuid($bannerId) : null;

            if (null === $banner) {
                yield null;
            } else {
                $campaign = $banner->campaign;
                yield [
                    'pay_from' => $campaign->source_address,
                    'pay_to' => AdsUtils::normalizeAddress(config('app.adshares_address')),
                    'serve_url' => str_replace('webserver', 'localhost:8101', $banner->serve_url),
                    'creative_sha1' => $banner->checksum,
                    'click_url' => route(
                        'log-network-click',
                        [
                            'id' => $banner->uuid,
                            'r' => Utils::urlSafeBase64Encode($banner->click_url),
                        ]
                    ),
                    'view_url' => route(
                        'log-network-view',
                        [
                            'id' => $banner->uuid,
                            'r' => Utils::urlSafeBase64Encode($banner->view_url),
                        ]
                    ),
                ];
            }
        }
    }
}
