<?php

namespace Adshares\Adserver\Services;

use Adshares\Helper\Filter;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCampaign;
use Adshares\Adserver\Models\Zone;

// use Adshares\Adserver\Services\Adselect;

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
                $impression_keywords['zone'] = $website->getHost().'/'.$zone->getId();
                $impression_keywords['banner_size'] = $zone->getWidth().'x'.$zone->getHeight();

//                 print_r($impression_keywords);exit;

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

                try {
                    // $zone instanceof Zone; // ?? Yodahack : what the hack
                    $bannerIds[] = NetworkBanner::where('creative_width', $zone->width)
                      ->where('creative_height', $zone->height)
                      ->whereIn('creative_type', $typeDefault)
                      ->get()->pluck('uuid')->random();
                } catch (\InvalidArgumentException $e) {
                    $bannerIds[] = '';
                }
            }
        }

        $banners = [];
        foreach ($bannerIds as $bannerId) {
            // TODO:
            $banner = $bannerId ? NetworkBanner::where('uuid', hex2bin($bannerId))->first() : null;

            if (!empty($banner)) {
                $campaign = NetworkCampaign::find($banner->network_campaign_id);
                $banners[] = [
                    'serve_url' => $banner->serve_url,
                    'creative_sha1' => $banner->creative_sha1,
                    'pay_from' => $campaign->adshares_address, // send this info to log
                    'click_url' => route('log-network-click', [
                        'id' => $banner->uuid,
                        'r' => Utils::urlSafeBase64Encode($banner->click_url),
                    ]),
                    'view_url' => route('log-network-view', [
                        'id' => $banner->uuid,
                        'r' => Utils::urlSafeBase64Encode($banner->view_url),
                    ]),
                ];
            } else {
                $banners[] = null;
                // TODO: discuss Jacek
            }
        }

        return $banners;
    }
}
