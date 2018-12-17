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

namespace Adshares\Adserver\Http\Controllers;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkEventLog;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Service\AdSelect;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use function urlencode;

class SupplyController extends Controller
{
    public function find(
        Request $request,
        AdUser $contextProvider,
        AdSelect $bannerFinder,
        string $data = null
    ) {
        $response = new Response();

        if ($request->headers->has('Origin')) {
            $response->headers->set('Access-Control-Allow-Origin', $request->headers->get('Origin'));
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $response->headers->set('Access-Control-Expose-Headers', 'X-Adshares-Cid, X-Adshares-Lid');
        }

        if ($data) {
        } elseif ('GET' === $request->getRealMethod()) {
            $data = $request->getQueryString();
        } elseif ('POST' === $request->getRealMethod()) {
            $data = $request->getContent();
        } elseif ('OPTIONS' === $request->getRealMethod()) {
            $response->setStatusCode(204);
            $response->headers->set('Access-Control-Max-Age', 1728000);

            return $response;
        } else {
            throw new BadRequestHttpException('Invalid method');
        }

        $decodedQueryData = Utils::decodeZones($data);
        $impressionId = $decodedQueryData['page']['iid'];

        $tid = Utils::attachOrProlongTrackingCookie(
            config('app.adserver_secret'),
            $request,
            $response,
            '',
            new DateTime(),
            $impressionId
        );

        if ($tid === null) {
            throw new NotFoundHttpException('User not found');
        }

        ['site' => $site, 'device' => $device] = Utils::getImpressionContext($request, $data);

        $userContext = $contextProvider->getUserContext(new ImpressionContext($site, $device, ['uid' => $tid]));
        $context = new ImpressionContext($site, $device, $userContext->toAdSelectPartialArray());

        $zones = Utils::decodeZones($data)['zones'];

        $banners = $bannerFinder->findBanners($zones, $context);

        return self::json($banners);
    }

    public function findScript(Request $request): StreamedResponse
    {
        $params = [
            json_encode($request->getSchemeAndHttpHost()),
            json_encode(config('app.aduser_external_location')),
            json_encode(config('app.website_banner_selector')),
        ];

        $jsPath = public_path('-/find.js');

        $response = new StreamedResponse();
        $response->setCallback(
            function () use ($jsPath, $params) {
                echo str_replace(
                    [
                        "'{{ ORIGIN }}'",
                        "'{{ ADUSER }}'",
                        "'{{ SELECTOR }}'",
                    ],
                    $params,
                    file_get_contents($jsPath)
                );
            }
        );

        $response->headers->set('Content-Type', 'text/javascript');

        $response->setCache(
            [
                'etag' => md5(md5_file($jsPath).implode(':', $params)),
                'last_modified' => new \DateTime('@'.filemtime($jsPath)),
                'max_age' => 3600 * 24 * 30,
                's_maxage' => 3600 * 24 * 30,
                'private' => false,
                'public' => true,
            ]
        );

        if (!$response->isNotModified($request)) {
            // TODO: ask Jacek
        }

        return $response;
    }

    public function logNetworkClick(
        Request $request,
        AdUser $contextProvider,
        string $bannerId
    ): RedirectResponse {
        if ($request->query->get('r')) {
            $url = Utils::urlSafeBase64Decode($request->query->get('r'));
            $request->query->remove('r');
        } else {
            $banner = NetworkBanner::where('uuid', hex2bin($bannerId))->first();

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
        $requestHeaders = $request->headers->all();

        $context = Utils::decodeZones($request->query->get('ctx'));
        $eventId = Utils::getRawTrackingId(Utils::createTrackingId(config('app.adserver_secret')));
        $trackingId = Utils::getRawTrackingId($request->cookies->get('tid')) ?: $logIp;
        $payFrom = $request->query->get('pfr');
        $payTo = AdsUtils::normalizeAddress(config('app.adshares_address'));
        $zoneId = $context['page']['zone'];
        $publisherId = Zone::fetchPublisherId($zoneId);
        $url = Utils::addUrlParameter($url, 'pto', $payTo);
        $url = Utils::addUrlParameter($url, 'pid', $publisherId);

        $response = new RedirectResponse($url);
        $response->send();

        $log = new NetworkEventLog();
        $log->event_id = $eventId;
        $log->banner_id = $bannerId;
        $log->user_id = $trackingId;
        $log->zone_id = $context['page']['zone'];
        $log->publisher_id = $publisherId;
        $log->pay_from = $payFrom;
        $log->ip = $logIp;
        $log->headers = $requestHeaders;
        $log->event_type = 'click';

        ['site' => $site, 'device' => $device] = Utils::getImpressionContext($request);
        $userContext = $contextProvider->getUserContext(new ImpressionContext($site, $device, ['uid' => $trackingId]));

        $log->context = new ImpressionContext($site, $device, $userContext->toAdSelectPartialArray());

        $log->save();

        return $response;
    }

    public function logNetworkView(Request $request, AdUser $contextProvider, string $bannerId): RedirectResponse
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
        $requestHeaders = $request->headers->all();

        $context = Utils::decodeZones($request->query->get('ctx'));
        $eventId = Utils::getRawTrackingId(Utils::createTrackingId(config('app.adserver_secret')));
        $trackingId = Utils::getRawTrackingId($request->cookies->get('tid')) ?: $logIp;
        $payFrom = $request->query->get('pfr');
        $payTo = AdsUtils::normalizeAddress(config('app.adshares_address'));
        $zoneId = $context['page']['zone'];
        $publisherId = Zone::fetchPublisherId($zoneId);

        $url = Utils::addUrlParameter($url, 'cid', $eventId);
        $url = Utils::addUrlParameter($url, 'pto', $payTo);
        $url = Utils::addUrlParameter($url, 'pid', $publisherId);

        $response = new RedirectResponse($url);
        $response->send();

        $log = new NetworkEventLog();
        $log->event_id = $eventId;
        $log->banner_id = $bannerId;
        $log->user_id = $trackingId;
        $log->zone_id = $zoneId;
        $log->publisher_id = $publisherId;
        $log->pay_from = $payFrom;
        $log->ip = $logIp;
        $log->headers = $requestHeaders;
        $log->event_type = 'view';

        ['site' => $site, 'device' => $device] = Utils::getImpressionContext($request);
        $userContext = $contextProvider->getUserContext(new ImpressionContext($site, $device, ['uid' => $trackingId]));

        $log->context = new ImpressionContext($site, $device, $userContext->toAdSelectPartialArray());

        $log->save();

        return $response;
    }

    /**
     * Create or prolong tracking cookie and connect it with AdUser.
     *
     * @param Request $request
     *
     * @return Response
     * @throws \Exception
     */
    public function register(Request $request): Response
    {
        $response = new Response();
        $impressionId = $request->query->get('iid');

        $trackingId = Utils::attachOrProlongTrackingCookie(
            config('app.adserver_secret'),
            $request,
            $response,
            '',
            new \DateTime(),
            $impressionId
        );

        $adUserUrl = sprintf(
            '%s/register/%s/%s/%s.gif',
            config('app.aduser_external_location'),
            urlencode(config('app.adserver_id')),
            $trackingId,
            $impressionId
        );

        $response->headers->set('Location', $adUserUrl);

        return $response;
    }
}
