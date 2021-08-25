<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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
use Adshares\Adserver\Models\NetworkCase;
use Adshares\Adserver\Models\NetworkCaseClick;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\NetworkImpression;
use Adshares\Adserver\Models\NetworkVectorsMeta;
use Adshares\Adserver\Models\ServeDomain;
use Adshares\Adserver\Models\SupplyBlacklistedDomain;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Adserver\Utilities\CssUtils;
use Adshares\Adserver\Utilities\DomainReader;
use Adshares\Adserver\Utilities\SqlUtils;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Service\AdSelect;
use DateTime;
use DateTimeInterface;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

use function urlencode;

class SupplyController extends Controller
{
    private const UNACCEPTABLE_PAGE_RANK = 0.0;

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

        if (!$data) {
            if ('GET' === $request->getRealMethod()) {
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
        }

        if (!$data) {
            throw new UnprocessableEntityHttpException();
        }

        if (false !== ($index = strpos($data, '&'))) {
            $data = substr($data, 0, $index);
        }

        try {
            $decodedQueryData = Utils::decodeZones($data);
        } catch (RuntimeException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }
        $zones = $decodedQueryData['zones'] ?? [];

        if (!$zones) {
            return self::json([]);
        }

        $zones = array_slice($zones, 0, config('app.max_page_zones'));

        if ($this->isPageBlacklisted($decodedQueryData['page']['url'] ?? '')) {
            throw new BadRequestHttpException('Site not accepted');
        }
        if (!config('app.allow_zone_in_iframe') && ($decodedQueryData['page']['frame'] ?? false)) {
            throw new BadRequestHttpException('Cannot run in iframe');
        }
        if ($decodedQueryData['page']['pop'] ?? false) {
            if (
                DomainReader::domain($decodedQueryData['page']['ref'] ?? '')
                != DomainReader::domain($decodedQueryData['page']['url'] ?? '')
            ) {
                throw new BadRequestHttpException('Bad request.');
            }
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

        $impressionContext = Utils::getPartialImpressionContext($request, $data, $tid);
        $userContext = $contextProvider->getUserContext($impressionContext);

        if ($userContext->isCrawler()) {
            return self::json([]);
        }

        if ($userContext->pageRank() <= self::UNACCEPTABLE_PAGE_RANK) {
            if ($userContext->pageRank() == Aduser::CPA_ONLY_PAGE_RANK) {
                foreach ($zones as &$zone) {
                    $zone['options']['cpa_only'] = true;
                }
            } elseif (config('app.env') != 'dev') {
                return self::json([]);
            }
        }

        $context = Utils::mergeImpressionContextAndUserContext($impressionContext, $userContext);
        $foundBanners = $bannerFinder->findBanners($zones, $context);

        NetworkImpression::register(
            Utils::hexUuidFromBase64UrlWithChecksum($impressionId),
            Utils::hexUuidFromBase64UrlWithChecksum($tid),
            $impressionContext,
            $userContext,
            $foundBanners,
            $zones
        );

        return self::json($foundBanners);
    }

    public function findScript(): StreamedResponse
    {
        $params = [
            config('app.main_js_tld') ? ServeDomain::current() : config('app.serve_base_url'),
            '.' . CssUtils::normalizeClass(config('app.adserver_id')),
        ];

        $jsPath = public_path('-/find.js');

        $response = new StreamedResponse();
        $response->setCallback(
            static function () use ($jsPath, $params) {
                echo str_replace(
                    [
                        '{{ ORIGIN }}',
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
                'last_modified' => new DateTime(),
                'max_age' => 3600 * 24 * 1,
                's_maxage' => 3600 * 24 * 1,
                'private' => false,
                'public' => true,
            ]
        );

        return $response;
    }

    public function logNetworkClick(Request $request, string $bannerId): RedirectResponse
    {
        $this->validateEventRequest($request);

        $url = $this->getRedirectionUrlFromQuery($request);

        if (!$url) {
            if (null === ($banner = NetworkBanner::fetchByPublicId($bannerId))) {
                throw new NotFoundHttpException();
            }
            $url = $banner->click_url;
        }

        $url = $this->addQueryStringToUrl($request, $url);

        $caseId = $request->query->get('cid');
        if (null === ($networkCase = NetworkCase::fetchByCaseId($caseId))) {
            throw new NotFoundHttpException();
        }

        $eventId = Utils::createCaseIdContainingEventType($caseId, 'click');
        $payTo = AdsUtils::normalizeAddress(config('app.adshares_address'));
        try {
            $zoneId = Utils::getZoneIdFromContext($request->query->get('ctx'));
        } catch (RuntimeException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }
        $publisherId = Zone::fetchPublisherPublicIdByPublicId($zoneId);
        $impressionId = $request->query->get('iid');

        $url = Utils::addUrlParameter($url, 'pto', $payTo);
        $url = Utils::addUrlParameter($url, 'pid', $publisherId);
        $url = Utils::addUrlParameter($url, 'eid', $eventId);
        $url = Utils::addUrlParameter($url, 'iid', $impressionId);

        $response = new RedirectResponse($url);
        $response->send();

        try {
            $networkCase->networkCaseClick()->save(new NetworkCaseClick());
        } catch (QueryException $queryException) {
            if (SqlUtils::isDuplicatedEntry($queryException)) {
                Log::info(sprintf('Duplicated click for case (%s)', $caseId));
            } else {
                Log::warning(
                    sprintf(
                        'During saving click for case (%s) occurred an error (%s)',
                        $caseId,
                        $queryException->getMessage()
                    )
                );
            }
        }

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
                $url .= '?' . $qString;
            } elseif ($qPos === strlen($url) - 1) {
                $url .= $qString;
            } else {
                $url .= '&' . $qString;
            }
        }

        return $url;
    }

    public function logNetworkView(Request $request, string $bannerId): RedirectResponse
    {
        $this->validateEventRequest($request);

        $impressionId = $request->query->get('iid');
        $networkImpression = NetworkImpression::fetchByImpressionId(
            Utils::hexUuidFromBase64UrlWithChecksum($impressionId)
        );
        if (null === $networkImpression) {
            throw new NotFoundHttpException();
        }

        $url = $this->getRedirectionUrlFromQuery($request);
        if ($url) {
            $url = $this->addQueryStringToUrl($request, $url);
        }

        $caseId = $request->query->get('cid');
        $eventId = Utils::createCaseIdContainingEventType($caseId, 'view');
        $payTo = AdsUtils::normalizeAddress(config('app.adshares_address'));
        try {
            $zoneId = Utils::getZoneIdFromContext($request->query->get('ctx'));
        } catch (RuntimeException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }
        $publisherId = Zone::fetchPublisherPublicIdByPublicId($zoneId);
        $siteId = Zone::fetchSitePublicIdByPublicId($zoneId);

        $url = Utils::addUrlParameter($url, 'pto', $payTo);
        $url = Utils::addUrlParameter($url, 'pid', $publisherId);
        $url = Utils::addUrlParameter($url, 'eid', $eventId);
        $url = Utils::addUrlParameter($url, 'iid', $impressionId);

        $response = new RedirectResponse($url);
        $response->send();

        $networkCase = NetworkCase::create(
            $caseId,
            $publisherId,
            $siteId,
            $zoneId,
            $bannerId
        );

        if (null !== $networkCase) {
            $networkImpression->networkCases()->save($networkCase);
        }

        return $response;
    }

    private function validateEventRequest(Request $request): void
    {
        if (
            !$request->query->has('r')
            || !$request->query->has('ctx')
            || !Utils::isUuidValid($request->query->get('cid'))
        ) {
            throw new BadRequestHttpException('Invalid parameters.');
        }
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

        $adUserEndpoint = config('app.aduser_serve_subdomain') ?
            ServeDomain::current(config('app.aduser_serve_subdomain')) :
            config('app.aduser_base_url');

        if ($adUserEndpoint) {
            $adUserUrl = sprintf(
                '%s/register/%s/%s/%s.html',
                $adUserEndpoint,
                urlencode(config('app.adserver_id')),
                $trackingId,
                $impressionId
            );

            $response->setStatusCode(\Symfony\Component\HttpFoundation\Response::HTTP_FOUND);
            $response->headers->set('Location', new SecureUrl($adUserUrl));
        }

        return $response;
    }

    public function why(Request $request): View
    {
        Validator::make(
            $request->all(),
            [
                'bid' => 'required|regex:/^[0-9a-f]{32}$/i',
                'cid' => 'required|regex:/^[0-9a-f]{32}$/i',
            ]
        )->validate();

        $bannerId = $request->query->get('bid');
        $caseId = $request->query->get('cid');

        if (null === ($banner = NetworkBanner::fetchByPublicId($bannerId))) {
            throw new NotFoundHttpException('No matching banner');
        }
        $campaign = $banner->campaign()->first();
        $networkHost = NetworkHost::fetchByHost($campaign->source_host);

        $info = $networkHost->info ?? null;

        $data = [
            'url' => $banner->serve_url,
            'supplyName' => config('app.name'),
            'supplyTermsUrl' => config('app.terms_url'),
            'supplyPrivacyUrl' => config('app.privacy_url'),
            'supplyPanelUrl' => config('app.adpanel_url'),
            'supplyBannerReportUrl' => new SecureUrl(
                route(
                    'report-ad',
                    [
                        'banner_id' => $bannerId,
                        'case_id' => $caseId,
                    ]
                )
            ),
            'supplyBannerRejectUrl' => config('app.adpanel_url') . '/publisher/classifier/' . $bannerId,
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

    public function reportAd(string $caseId, string $bannerId): string
    {
        if (!Utils::isUuidValid($caseId) || !Utils::isUuidValid($bannerId)) {
            throw new UnprocessableEntityHttpException();
        }

        if (null === ($case = NetworkCase::fetchByCaseId($caseId))) {
            throw new NotFoundHttpException('Missing case');
        }

        if ($case->banner_id !== $bannerId) {
            throw new BadRequestHttpException('Wrong banner id');
        }

        $userId = User::fetchByUuid($case->publisher_id)->id;

        Storage::disk('local')->put(
            'reported-ads.txt',
            sprintf('%s;%s', $userId, $bannerId)
        );

        return 'Thank you for reporting ad.';
    }

    private function isPageBlacklisted(string $url): bool
    {
        $domain = DomainReader::domain($url);

        return SupplyBlacklistedDomain::isDomainBlacklisted($domain);
    }

    public function targetingReachList(): Response
    {
        if (null === ($networkHost = NetworkHost::fetchByAddress((string)config('app.adshares_address')))) {
            return response(
                ['code' => Response::HTTP_INTERNAL_SERVER_ERROR, 'message' => 'Cannot get adserver id'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        if (null === ($meta = NetworkVectorsMeta::fetchByNetworkHostId($networkHost->id))) {
            return response(
                ['code' => Response::HTTP_INTERNAL_SERVER_ERROR, 'message' => 'Cannot get adserver meta'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        $rows = DB::table('network_vectors')->select(
            [
                'key',
                'occurrences',
                'cpm_25',
                'cpm_50',
                'cpm_75',
                'negation_cpm_25',
                'negation_cpm_50',
                'negation_cpm_75',
                'data',
            ]
        )->where('network_host_id', $networkHost->id)->get();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'key' => $row->key,
                'occurrences' => $row->occurrences,
                'cpm_25' => $row->cpm_25,
                'cpm_50' => $row->cpm_50,
                'cpm_75' => $row->cpm_75,
                'negation_cpm_25' => $row->negation_cpm_25,
                'negation_cpm_50' => $row->negation_cpm_50,
                'negation_cpm_75' => $row->negation_cpm_75,
                'data' => Utils::urlSafeBase64Encode($row->data),
            ];
        }

        return response(
            [
                'meta' => [
                    'total_events_count' => $meta->total_events_count,
                    'updated_at' => $meta->updated_at->format(DateTimeInterface::ATOM),
                ],
                'categories' => $result,
            ]
        );
    }
}
