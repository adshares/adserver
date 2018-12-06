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
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\HttpClient\JsonRpc;
use Adshares\Adserver\HttpClient\JsonRpc\Procedure;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Supply\Application\Dto\FoundBanners;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Service\BannerFinder;
use Adshares\Supply\Application\Service\InventoryExporter;
use Adshares\Supply\Domain\Model\Campaign;
use Generator;
use function iterator_to_array;

final class JsonRpcAdSelectClient implements BannerFinder, InventoryExporter
{
    private const METHOD_CAMPAIGN_UPDATE = 'campaign_update';

    private const METHOD_BANNER_SELECT = 'banner_select';

    /** @var JsonRpc */
    private $client;

    public function __construct(JsonRpc $client)
    {
        $this->client = $client;
    }

    public function findBanners(array $zones, ImpressionContext $context): FoundBanners
    {
        $result = $this->client->call(
            new Procedure(
                self::METHOD_BANNER_SELECT, $context->adSelectRequestParams($zones)
            )
        );

        $bannerIds = $this->fixBannerOrdering($zones, iterator_to_array($this->prepare($result->toArray())));

        $banners = iterator_to_array($this->find(iterator_to_array($bannerIds)));

        return new FoundBanners($banners);
    }

    private function fixBannerOrdering(array $zones, array $banners): Generator
    {
        foreach ($zones as $key => $zone) {
            yield $banners[$key] ?? null;
        }
    }

    private function prepare(array $bannerIds): Generator
    {
        foreach ($bannerIds as $item) {
            if (isset($item['request_id'])) {
                yield  $item['request_id'] => $item;
            } else {
                yield null;
            }
        }
    }

    private function find(array $bannerIds): Generator
    {
        foreach ($bannerIds as $bannerId) {
            $banner = $bannerId ? NetworkBanner::where('uuid', hex2bin($bannerId))->first() : NetworkBanner::first();

            if (empty($banner)) {
                yield null;
            } else {
                $campaign = NetworkCampaign::find($banner->network_campaign_id);
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

    public function exportInventory(Campaign $campaign): void
    {
        $procedure = new Procedure(
            self::METHOD_CAMPAIGN_UPDATE, CampaignMapper::map($campaign)
        );

        $this->client->call($procedure);
    }
}
