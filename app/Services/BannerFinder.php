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

namespace Adshares\Adserver\Services;

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Helper\Filter;

/**
 * Returns random banners. Used if adselect service is not available. Shoud probalby be moved to Adselect class.
 */
class BannerFinder
{
    public static function getBestBanners(array $zones, array $keywords)
    {
        $typeDefault = [
            'html',
            'image',
        ];

        // TODO: adselect

        // $adselectService = $container->has('adselect') ? $container->get('adselect') : null;
        // $adselectService instanceof Adselect;

        $bannerIds = [];
        if (false && $adselectService) {
            $requests = [];
            foreach ($zones as $i => $zoneInfo) {
                $zone = Zone::find($zoneInfo['zone']);
                $zone instanceof Zone;

                $website = $zone->getWebsite();

                $impression_keywords = $keywords;
                $impression_keywords['zone'] = $website->getHost() . '/' . $zone->getId();
                $impression_keywords['banner_size'] = $zone->getWidth() . 'x' . $zone->getHeight();

                $filters = Filter::getFilter($website->getRequire(), $website->getExclude());
//                 $filters['require'][] = [
//                     'keyword' => 'banner_size',
//                     'filter' => [
//                         'type' => '=',
//                         'args' => $impression_keywords['banner_size']
//                     ]
//                 ];

                $requests[] = [
                    'request_id' => $i,
                    'publisher_id' => $zone->getWebsite()->getUser()->getId(),
                    'user_id' => $impression_keywords['user_id'],
                    'banner_size' => $impression_keywords['banner_size'],
                    'keywords' => Utils::flattenKeywords($impression_keywords),
                    'banner_filters' => $filters,
                ];
                $bannerIds[$i] = null;
            }

            $responses = $adselectService->getBanners($requests);
            foreach ($responses as $response) {
                $bannerIds[$response['request_id']] = $response['banner_id'];
            }
//             ksort($bannerIds);
        } else {
            foreach ($zones as $zoneInfo) {
                $zone = Zone::find($zoneInfo['zone']);

                if ($zone) {
                    try {
                        // $zone instanceof Zone; // ?? Yodahack : what the hack
                        $bannerIds[] = NetworkBanner::where('creative_width', $zone->width)
                            ->where('creative_height', $zone->height)
                            ->whereIn('creative_type', $typeDefault)
                            ->get()->pluck('uuid')->random();
                    } catch (\InvalidArgumentException $e) {
                        $bannerIds[] = '';
                    }
                } else {
                    $bannerIds[] = md5(rand());
                }
            }
        }

        $banners = [];
        foreach ($bannerIds as $bannerId) {
            $banners[] = [
                'serve_url' => config('app.app_url') . '/img/logo_x.png',
                'creative_sha1' => sha1_file(public_path('img/logo_x.png')),
                'pay_from' => AdsUtils::normalizeAddress(config('app.adshares_address')), // send this info to log
                'click_url' => route('log-network-click', [
                    'id' => '',
                    'r' => Utils::urlSafeBase64Encode(config('app.app_url')),
                ]),
                'view_url' => route('log-network-view', [
                    'id' => '',
                    'r' => Utils::urlSafeBase64Encode(config('app.app_url')),
                ]),
            ];

//            // TODO: fix
//            $banner = $bannerId ? NetworkBanner::where('uuid', hex2bin($bannerId))->first() : null;
//
//            if (!empty($banner)) {
//                $campaign = NetworkCampaign::find($banner->network_campaign_id);
//                $banners[] = [
//                    'serve_url' => $banner->serve_url,
//                    'creative_sha1' => $banner->creative_sha1,
//                    'pay_from' => $campaign->adshares_address, // send this info to log
//                    'click_url' => route('log-network-click', [
//                        'id' => $banner->uuid,
//                        'r' => Utils::urlSafeBase64Encode($banner->click_url),
//                    ]),
//                    'view_url' => route('log-network-view', [
//                        'id' => $banner->uuid,
//                        'r' => Utils::urlSafeBase64Encode($banner->view_url),
//                    ]),
//                ];
//            }
        }

        return $banners;
    }
}
