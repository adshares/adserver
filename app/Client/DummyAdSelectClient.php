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

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\Zone;
use Adshares\Supply\Application\Dto\FoundBanners;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Service\BannerFinder;
use Adshares\Supply\Domain\Model\Campaign;
use function array_map;
use function rand;
use function str_replace;

final class DummyAdSelectClient implements BannerFinder
{
    public function findBanners(ImpressionContext $context): FoundBanners
    {
        $banners = $this->getBestBanners($context->zones(), $context->keywords());

        return new FoundBanners($banners);
    }

    private function getBestBanners(array $zones, array $keywords): array
    {
        $typeDefault = [
            'image',
        ];

        $keywords = array_map(function (string $keyword) {
            return str_replace('accio:', '', $keyword);
        }, $keywords);

        $key = $keywords[random_int(0, count($keywords) - 1)];

        $bannerIds = [];
        foreach ($zones as $zoneInfo) {
            $zone = Zone::find($zoneInfo['zone']);

            if ($zone) {
                try {
                    $pluck = DB::table('network_banners')
                        ->join('network_campaigns', 'network_banners.network_campaign_id', '=', 'network_campaigns.id')
                        ->select('network_banners.uuid')
//                        ->where('network_campaigns.targeting_requires', 'LIKE', "%$key%")
                        ->whereRaw("(network_campaigns.targeting_requires LIKE ? OR network_campaigns.targeting_requires = '[]')",
                            "%$key%")
                        ->whereRaw("(network_campaigns.targeting_excludes NOT LIKE ? OR network_campaigns.targeting_excludes = '[]')",
                            "%$key%")
                        ->where('network_campaigns.status', Campaign::STATUS_ACTIVE)
                        ->where('network_banners.width', $zone->width)
                        ->where('network_banners.height', $zone->height)
                        ->whereIn('type', $typeDefault)
                        ->get();
                    $bannerIds[] = bin2hex($pluck->random()->uuid);
                } catch (\InvalidArgumentException $e) {
                    $bannerIds[] = '';
                }
            } else {
                $bannerIds[] = md5(rand());
            }
        }

        $banners = [];
        foreach ($bannerIds as $bannerId) {
            $banner = $bannerId ? NetworkBanner::where('uuid', hex2bin($bannerId))->first() : NetworkBanner::first();

            if (!empty($banner)) {
                $campaign = NetworkCampaign::find($banner->network_campaign_id);
                $banners[] = [//TODO: change it to proper value
                    'serve_url' => str_replace('webserver', 'localhost:8101', $banner->serve_url),
                    'creative_sha1' => $banner->checksum,
                    'pay_from' => $campaign->source_address, // send this info to log
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

        return $banners;
    }
}
