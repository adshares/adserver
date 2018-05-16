<?php
namespace Adshares\Supply;

use Adshares\Entity\NetworkCampaign;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Routing\Router;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Adshares\Helper\Utils;
use Symfony\Component\DependencyInjection\Container;
use Doctrine\ORM\Query;
use Adshares\Entity\NetworkBanner;
use Adshares\Services\Adselect;
use Adshares\Entity\Zone;
use Adshares\Helper\Filter;

/**
 *

 cond = (name = 'screen_width AND 800 BETWEEN min AND max OR name ='Browser' AND "Chrome" BETWEEN min AND max))

 SELECT b.id, b.price_amount,  b.price_amount * IFNULL(1 + (SELECT SUM(i.modifier) FROM `BannerModifier` i WHERE i.banner_id = b.id AND (i.name = 'screen_width' AND 500 BETWEEN i.min AND i.max OR i.name = 'screen_height' AND 100 BETWEEN i.min AND i.max OR i.name = 'browser' AND 'Chrome' BETWEEN min AND max) )/100, 1) score
 FROM Banner b
 WHERE
 NOT EXISTS (SELECT e.banner_id FROM `BannerExclude` e WHERE e.banner_id = b.id AND (e.name = 'screen_width' AND 500 BETWEEN e.min AND e.max OR e.name = 'screen_height' AND 100 BETWEEN e.min AND e.max OR e.name = 'browser' AND 'Chrome' BETWEEN min AND max))
 AND require_count = (SELECT COUNT(DISTINCT name) FROM `BannerRequire` r WHERE r.banner_id = b.id AND (r.name = 'screen_width' AND 500 BETWEEN r.min AND r.max OR r.name = 'screen_height' AND 500 BETWEEN r.min AND r.max OR r.name = 'browser' AND 'Chrome' BETWEEN min AND max))
 GROUP BY b.id ORDER BY score DESC

 */


/**
 *
 * Returns random banners. Used if adselect service is not available. Shoud probalby be moved to Adselect class.
 *
 */
class BannerFinder
{
    public static function getBestBanners(array $zones, array $keywords)
    {
        $typeDefault = [
            'html',
            'image'
        ];

        $adselectService = $container->has('adselect') ? $container->get('adselect') : null;
        $adselectService instanceof Adselect;

        $bannerIds = [];


        if ($adselectService) {
            $requests = [];
            foreach ($zones as $i => $zoneInfo) {
                $zone = Zone::getRepository($em)->find($zoneInfo['zone']);
                $zone instanceof Zone;

                $website = $zone->getWebsite();

                $impression_keywords = $keywords;
                $impression_keywords['zone'] = $website->getHost() . '/' . $zone->getId();
                $impression_keywords['banner_size'] = $zone->getWidth() . 'x' . $zone->getHeight();

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
                    'banner_filters' => $filters
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
                $zone = Zone::getRepository($em)->find($zoneInfo['zone']);
                $zone instanceof Zone;
                $bannerList = NetworkBanner::getRepository($em)->createQueryBuilder('b')
                    ->andWhere('b.creative_width = :width')
                    ->andWhere('b.creative_height = :height')
                    ->andWhere('b.creative_type IN (:type)')
                    ->setParameters([
                    'width' => $zone->getWidth(),
                    'height' => $zone->getHeight(),
                    'type' => $typeDefault
                    ])
                    ->getQuery()
                    ->getResult(Query::HYDRATE_SIMPLEOBJECT);

                if (! $bannerList) {
                    $bannerIds[] = null;
                    continue;
                }
                $bannerIds[] = $bannerList[array_rand($bannerList)]->getUuid();
            }
        }

        $router = $container->get('router');
        assert($router instanceof Router);

        $banners = [];
        foreach ($bannerIds as $bannerId) {
            $banner = $bannerId ? NetworkBanner::getRepository($em)->findOneBy(['uuid' => $bannerId]) : null;


            if ($banner instanceof NetworkBanner) {
                $campaign = NetworkCampaign::getRepository($em)->find($banner->getCampaignId());

                $click_url = $router->generate('log_network_click', [
                    'id' => $banner->getUuid(),
                    'r' => Utils::UrlSafeBase64Encode($banner->getClickUrl())
                ], UrlGeneratorInterface::ABSOLUTE_URL);
                $view_url = $router->generate('log_network_view', [
                    'id' => $banner->getUuid(),
                    'r' => Utils::UrlSafeBase64Encode($banner->getViewUrl())
                ], UrlGeneratorInterface::ABSOLUTE_URL);
                $banners[] = [
                    'serve_url' => $banner->getServeUrl(),
                    'creative_sha1' => $banner->getCreativeSha1(),
                    'pay_from' => $campaign->getAdsharesAddress(), // send this info to log
                    'click_url' => $click_url,
                    'view_url' => $view_url
                ];
            } else {
                $banners[] = null;
            }
        }

        return $banners;
    }
}
