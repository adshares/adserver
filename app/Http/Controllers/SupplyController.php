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
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\SupplyBlacklistedDomain;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Adserver\Utilities\DomainReader;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Supply\Application\Service\AdSelect;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
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
        }

        if ($data) {
        } elseif ('GET' === $request->getRealMethod()) {
            $data = $request->getQueryString();
        } elseif ('POST' === $request->getRealMethod()) {
            $data = (string)$request->getContent();
        } elseif ('OPTIONS' === $request->getRealMethod()) {
            $response->setStatusCode(Response::HTTP_NO_CONTENT);
            $response->headers->set('Access-Control-Max-Age', 1728000);

            return $response;
        } else {
            throw new BadRequestHttpException('Invalid method');
        }

        if (!$data) {
            throw new UnprocessableEntityHttpException();
        }

        if (false !== ($index = strpos($data, '&'))) {
            $data = substr($data, 0, $index);
        }

        $decodedQueryData = Utils::decodeZones($data);
        $zones = $decodedQueryData['zones'] ?? [];

        if (!$zones || $this->isBlacklisted($decodedQueryData)) {
            return self::json([]);
        }

        $impressionId = $decodedQueryData['page']['iid'];

        $tid = Utils::attachOrProlongTrackingCookie(
            $request,
            $response,
            '',
            new DateTime(),
            $impressionId
        );

        if ($tid === null) {
            throw new NotFoundHttpException('User not found');
        }

        $context = Utils::getFullContext($request, $contextProvider, $data, $tid);

        return self::json($bannerFinder->findBanners($zones, $context));
    }

    public function findScript(): StreamedResponse
    {
        $params = [
            config('app.serve_base_url'),
            config('app.aduser_base_url'),
            '.'.config('app.adserver_id'),
        ];

        $jsPath = public_path('-/find.js');

        $response = new StreamedResponse();
        $response->setCallback(
            static function () use ($jsPath, $params) {
                echo str_replace(
                    [
                        '{{ ORIGIN }}',
                        '{{ ADUSER }}',
                        '{{ SELECTOR }}',
                    ],
                    $params,
                    file_get_contents($jsPath)
                );
            }
        );

        $response->headers->set('Content-Type', 'text/javascript');
        $response->setCache(
            [
                'last_modified' => new \DateTime(),
                'max_age' => 3600 * 24 * 1,
                's_maxage' => 3600 * 24 * 1,
                'private' => false,
                'public' => true,
            ]
        );

        return $response;
    }

    public function logNetworkClick(
        Request $request,
        AdUser $contextProvider,
        string $bannerId
    ): RedirectResponse {
        $this->validateEventRequest($request);

        $url = $this->getRedirectionUrlFromQuery($request);

        if (!$url) {
            $banner = NetworkBanner::where('uuid', hex2bin($bannerId))->first();

            if (!$banner) {
                throw new NotFoundHttpException();
            }

            $url = $banner->click_url;
        }

        $url = $this->addQueryStringToUrl($request, $url);

        $requestHeaders = $request->headers->all();

        $caseId = $request->query->get('cid');
        $eventId = Utils::createCaseIdContainingEventType($caseId, NetworkEventLog::TYPE_CLICK);
        $trackingId = $request->cookies->get('tid')
            ? Utils::hexUuidFromBase64UrlWithChecksum($request->cookies->get('tid'))
            : $caseId;
        $payFrom = $request->query->get('pfr');
        $payTo = AdsUtils::normalizeAddress(config('app.adshares_address'));
        $zoneId = Utils::getZoneFromContext($request->query->get('ctx'));

        $publisherId = Zone::fetchPublisherPublicIdByPublicId($zoneId);
        $siteId = Zone::fetchSitePublicIdByPublicId($zoneId);

        $impressionId = $request->query->get('iid');

        $url = Utils::addUrlParameter($url, 'pto', $payTo);
        $url = Utils::addUrlParameter($url, 'pid', $publisherId);
        $url = Utils::addUrlParameter($url, 'eid', $eventId);
        $url = Utils::addUrlParameter($url, 'iid', $impressionId);

        $response = new RedirectResponse($url);
        $response->send();

        $context = Utils::getFullContext($request, $contextProvider);

        NetworkEventLog::create(
            $caseId,
            $eventId,
            $bannerId,
            $zoneId,
            $trackingId,
            $publisherId,
            $siteId,
            $payFrom,
            bin2hex(inet_pton($request->getClientIp())),
            $requestHeaders,
            $context,
            NetworkEventLog::TYPE_CLICK
        );

        NetworkEventLog::eventClicked($caseId);

        return $response;
    }

    private function getRedirectionUrlFromQuery(Request $request): string
    {
        if ($request->query->get('r')) {
            $url = Utils::urlSafeBase64Decode($request->query->get('r'));
            $request->query->remove('r');

            return $url;
        }

        return '';
    }

    private function addQueryStringToUrl(Request $request, string $url): string
    {
        $qString = http_build_query($request->query->all());
        if ($qString) {
            $qPos = strpos($url, '?');

            if (false === $qPos) {
                $url .= '?'.$qString;
            } elseif ($qPos === strlen($url) - 1) {
                $url .= $qString;
            } else {
                $url .= '&'.$qString;
            }
        }

        return $url;
    }

    public function logNetworkView(Request $request, AdUser $contextProvider, string $bannerId): RedirectResponse
    {
        $this->validateEventRequest($request);

        $url = $this->getRedirectionUrlFromQuery($request);
        if ($url) {
            $url = $this->addQueryStringToUrl($request, $url);
        }

        $requestHeaders = $request->headers->all();

        $caseId = $request->query->get('cid');
        $eventId = Utils::createCaseIdContainingEventType($caseId, NetworkEventLog::TYPE_VIEW);
        $trackingId = $request->cookies->get('tid')
            ? Utils::hexUuidFromBase64UrlWithChecksum($request->cookies->get('tid'))
            : $caseId;
        $payFrom = $request->query->get('pfr');
        $payTo = AdsUtils::normalizeAddress(config('app.adshares_address'));
        $zoneId = Utils::getZoneFromContext($request->query->get('ctx'));
        $publisherId = Zone::fetchPublisherPublicIdByPublicId($zoneId);
        $siteId = Zone::fetchSitePublicIdByPublicId($zoneId);

        $impressionId = $request->query->get('iid');

        $url = Utils::addUrlParameter($url, 'pto', $payTo);
        $url = Utils::addUrlParameter($url, 'pid', $publisherId);
        $url = Utils::addUrlParameter($url, 'eid', $eventId);
        $url = Utils::addUrlParameter($url, 'iid', $impressionId);

        $response = new RedirectResponse($url);
        $response->send();

        $context = Utils::getFullContext($request, $contextProvider);

        NetworkEventLog::create(
            $caseId,
            $eventId,
            $bannerId,
            $zoneId,
            $trackingId,
            $publisherId,
            $siteId,
            $payFrom,
            bin2hex(inet_pton($request->getClientIp())),
            $requestHeaders,
            $context,
            NetworkEventLog::TYPE_VIEW
        );

        return $response;
    }

    private function validateEventRequest(Request $request): void
    {
        if (!$request->query->has('r')
            || !$request->query->has('ctx')
            || !$this->isCidValid($request->query->get('cid'))
        ) {
            throw new BadRequestHttpException('Invalid parameters.');
        }
    }

    private function isCidValid($cid): bool
    {
        return is_string($cid) && 32 === strlen($cid);
    }

    /**
     * Create or prolong tracking cookie and connect it with AdUser.
     *
     * @param Request $request
     *
     * @return Response
     * @throws Exception
     */
    public function register(Request $request): Response
    {
        $response = new Response();
        $impressionId = $request->query->get('iid');

        $trackingId = Utils::attachOrProlongTrackingCookie(
            $request,
            $response,
            '',
            new DateTime(),
            $impressionId
        );

        $adUserUrl = sprintf(
            '%s/register/%s/%s/%s.htm',
            config('app.aduser_base_url'),
            urlencode(config('app.adserver_id')),
            $trackingId,
            $impressionId
        );

        $response->headers->set('Location', SecureUrl::change($adUserUrl));

        return $response;
    }

    public function why(Request $request): View
    {
        $bannerId = $request->query->get('bid');

        $banner = NetworkBanner::fetchByPublicId($bannerId);
        $campaign = $banner->campaign()->first();
        $networkHost = NetworkHost::fetchByHost($campaign->source_host);

        $info = $networkHost->info ?? null;

        $data = [
            'url' => $banner->serve_url,
            'supplyName' => config('app.name'),
            'supplyTermsUrl' => config('app.terms_url'),
            'supplyPrivacyUrl' => config('app.privacy_url'),
            'supplyPanelUrl' => config('app.adpanel_url'),
            'demand' => false,
            'bannerType' => $banner->type,
        ];

        if ($info) {
            $data = array_merge(
                $data,
                [
                    'demand' => true,
                    'demandName' => $info->getName(),
                    'demandTermsUrl' => $info->getTermsUrl() ?? null,
                    'demandPrivacyUrl' => $info->getPrivacyUrl() ?? null,
                    'demandPanelUrl' => $info->getPanelUrl(),
                ]
            );
        }

        return view(
            'supply/why',
            $data
        );
    }

    private function isBlacklisted(array $decodedQueryData): bool
    {
        $url = $decodedQueryData['page']['url'];
        $domain = DomainReader::domain($url);

        return SupplyBlacklistedDomain::isDomainBlacklisted($domain);
    }
}
