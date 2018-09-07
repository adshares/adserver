<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute it and/or modify it
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
 * along with AdServer.  If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Http\Controllers;

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\NetworkEventLog;
use Adshares\Adserver\Services\Adselect;
use Adshares\Adserver\Services\BannerFinder;
use Adshares\Adserver\Utilities\AdsUtils;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

// use Adshares\Services\Adselect;

// TODO: review request headers // extract & organize ??

/**
 * HTTP api that is used by supply adserver to display banners and log relevant events.
 */
class SupplyController extends Controller
{
    public function find(Request $request)
    {
        $response = new Response();

        if ($request->headers->has('Origin')) {
            $response->headers->set('Access-Control-Allow-Origin', $request->headers->get('Origin'));
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        }

        if ('GET' == $request->getRealMethod()) {
            $data = $request->getQueryString();
        } elseif ('POST' == $request->getRealMethod()) {
            $data = $request->getContent();
        } elseif ('OPTIONS' == $request->getRealMethod()) {
            $response->setStatusCode(204);
            $response->headers->set('Access-Control-Max-Age', 1728000);

            return $response;
        }

        $decoded = Utils::decodeZones($data);
//         print_r($decoded);exit;
        $zones = $decoded['zones'];

        $tid = Utils::attachTrackingCookie(config('app.adserver_secret'), $request, $response, '', new \DateTime());

        // use adselect here
        $context = Utils::getImpressionContext($request, $data);

        $impressionId = $decoded['page']['iid'];
        if ($impressionId) {
            $aduser_endpoint = config('app.aduser_endpoint');
            if ($aduser_endpoint) {
                $userdata = (array) json_decode(file_get_contents("{$aduser_endpoint}/getData/{$impressionId}"), true);
            } else {
                $userdata = [];
            }
        }

        $keywords = array_merge($context, $userdata);

        $banners = BannerFinder::getBestBanners($zones, $keywords);

        foreach ($banners as &$banner) {
            if ($banner) {
                $banner['pay_to'] = AdsUtils::normalizeAddress(config('app.adshares_address'));
            }
        }

        $response->setContent(json_encode($banners, JSON_PRETTY_PRINT));

        return $response;
    }

    // we do it here because ORIGIN may be configured elsewhere with randomization of hostname
    public function findScript(Request $request)
    {
        $params = [json_encode($request->getSchemeAndHttpHost()), json_encode(config('app.aduser_endpoint'))];

        $jsPath = public_path('-/find.js');

        $response = new StreamedResponse();
        $response->setCallback(function () use ($jsPath, $request, $params) {
            echo str_replace([
                "'{{ ORIGIN }}'",
                "'{{ ADUSER }}'",
            ], $params, file_get_contents($jsPath));
        });

        $response->headers->set('Content-Type', 'text/javascript');

        $response->setCache(array(
            'etag' => md5(md5_file($jsPath).implode(':', $params)),
            'last_modified' => new \DateTime('@'.filemtime($jsPath)),
            'max_age' => 3600 * 24 * 30,
            's_maxage' => 3600 * 24 * 30,
            'private' => false,
            'public' => true,
        ));

        if (!$response->isNotModified($request)) {
            // TODO: ask Jacek
        }

        return $response;
    }

    public function logNetworkClick(Request $request, Adselect $adselect, $id)
    {
        if ($request->query->get('r')) {
            $url = Utils::urlSafeBase64Decode($request->query->get('r'));
            $request->query->remove('r');
        } else {
            $banner = NetworkBanner::where('uuid', hex2bin($id))->first();

            if (!$banner) {
                throw new NotFoundHttpException();
            }

            $url = $banner->click_url;
        }

        $qString = http_build_query($request->query->all());
        if ($qString) {
            $qPos = strpos($url, '?');

            if (false === $qPos) {
                $url .= '?'.$qString;
            } elseif ($qPos == strlen($url) - 1) {
                $url .= $qString;
            } else {
                $url .= '&'.$qString;
            }
        }

        $logIp = bin2hex(inet_pton($request->getClientIp()));

        $cid = Utils::getRawTrackingId($request->query->get('cid'));
        $tid = Utils::getRawTrackingId($request->cookies->get('tid')) ?: $logIp;
        // TODO get user / website / zone
        $payFrom = $request->query->get('pfr');

        $log = new NetworkEventLog();
        $log->cid = $cid;
        $log->banner_id = $id;
        $log->pay_from = $payFrom;
        $log->tid = $tid;
        $log->ip = $logIp;
        $log->event_type = 'click';
        //         $log->setContext(Utils::getImpressionContext($this->container, $request));

        $log->save();

        $url = Utils::addUrlParameter($url, 'pid', $log->id);

        $adselect->addImpressions([
            $log->getAdselectJson(),
        ]);

        $response = new RedirectResponse($url);

        return $response;
    }

    public function logNetworkView(Request $request, Adselect $adselect, $id)
    {
        if ($request->query->get('r')) {
            $url = Utils::urlSafeBase64Decode($request->query->get('r'));
            $request->query->remove('r');
        }

        $qString = http_build_query($request->query->all());
        if ($qString) {
            $qPos = strpos($url, '?');

            if (false === $qPos) {
                $url .= '?'.$qString;
            } elseif ($qPos == strlen($url) - 1) {
                $url .= $qString;
            } else {
                $url .= '&'.$qString;
            }
        }

        $logIp = bin2hex(inet_pton($request->getClientIp()));

        $cid = Utils::getRawTrackingId($request->query->get('cid'));
        $tid = Utils::getRawTrackingId($request->cookies->get('tid')) ?: $logIp;
        $payFrom = $request->query->get('pfr');

        $log = new NetworkEventLog();
        $log->cid = $cid;
        $log->banner_id = $id;
        $log->pay_from = $payFrom;
        $log->tid = $tid;
        $log->ip = $logIp;
        $log->event_type = 'view';
        $log->context = Utils::getImpressionContext($request);

        // GET kewords from aduser
        $impressionId = $request->query->get('iid');
        $aduser_endpoint = config('app.aduser_endpoint');

        if (empty($aduser_endpoint) || empty($impressionId)) {
            $log->save();
        // TODO: process?
        } else {
            $userdata = json_decode(file_get_contents("{$aduser_endpoint}/getData/{$impressionId}"), true);

            $log->our_userdata = $userdata['user']['keywords'];
            $log->human_score = $userdata['user']['human_score'];
            // $log->user_id = $userdata['user']['user_id']; // TODO: review process and data
            $log->save();
        }

        $adselect->addImpressions([
            $log->getAdselectJson(),
        ]);

        $backUrl = route('log-network-click', ['log_id' => $log->id]);

        $url = Utils::addUrlParameter($url, 'pid', $log->id);
        $url = Utils::addUrlParameter($url, 'k', Utils::urlSafeBase64Encode(json_encode($log->our_userdata)));
        $url = Utils::addUrlParameter($url, 'r', Utils::urlSafeBase64Encode($backUrl));

        $response = new RedirectResponse($url);

        return $response;
    }

    public function logNetworkKeywords(Request $request, $log_id)
    {
        $source = $request->query->get('s');
        $keywords = json_decode(Utils::urlSafeBase64Decode($request->query->get('k')), true);

        $log = NetworkEventLog::find($log_id);
        if ($log) {
            $log->their_userdata = $keywords;
            $log->save();
        }
        //         $keywords = print_r($keywords, 1);
        //         $response = new Response("nKeywords logId={$log_id} source={$source} keywords={$keywords}");
        //         return $response;

        $response = new Response();
        //transparent 1px gif
        $response->setContent(base64_decode('R0lGODlhAQABAIABAP///wAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=='));
        $response->headers->set('Content-Type', 'image/gif');

        return $response;
    }
}
