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
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\NetworkCase;
use Adshares\Adserver\Models\NetworkCaseClick;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\NetworkImpression;
use Adshares\Adserver\Models\NetworkVectorsMeta;
use Adshares\Adserver\Models\ServeDomain;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\SupplyBlacklistedDomain;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Rules\PayoutAddressRule;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Adserver\Utilities\CssUtils;
use Adshares\Adserver\Utilities\DomainReader;
use Adshares\Adserver\Utilities\SqlUtils;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Dto\FoundBanners;
use Adshares\Supply\Application\Service\AdSelect;
use Adshares\Supply\Domain\ValueObject\Size;
use DateTime;
use DateTimeInterface;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

use function GuzzleHttp\Psr7\parse_query;

class SupplyController extends Controller
{
    private const UNACCEPTABLE_PAGE_RANK = 0.0;

    public function findJson(
        Request $request,
        AdUser $contextProvider,
        AdSelect $bannerFinder
    ) {

        $validated = $request->validate(
            [
                'pay_to'           => ['required', new PayoutAddressRule()],
                'view_id'          => ['required', 'regex:#^[A-Za-z0-9+/_-]+[=]{0,3}$#'],
                'zone_name'        => ['sometimes', 'regex:/^[0-9a-z -_]+$/i'],
                'width'            => ['required', 'numeric', 'gt:0'],
                'height'           => ['required', 'numeric', 'gt:0'],
                'min_dpi'          => ['sometimes', 'numeric', 'gt:0'],
                'type'             => Rule::in(Banner::types()),
                'exclude'          => ['sometimes', 'array:quality,category'],
                'exclude.*'        => ['sometimes', 'array'],
                'context'          => ['required', 'array:user,device,site'],
                'context.*'        => ['required', 'array'],
                'context.site.url' => ['required', 'url'],
            ]
        );

        $validated['min_dpi'] = $validated['min_dpi'] ?? 1;
        $validated['zone_name'] = $validated['zone_name'] ?? 'default';

        $payoutAddress = WalletAddress::fromString($validated['pay_to']);
        $user = User::fetchByWalletAddress($payoutAddress);

        if (!$user) {
            if (Config::isTrueOnly(Config::AUTO_REGISTRATION_ENABLED)) {
                $user = User::registerWithWallet($payoutAddress, true);
            } else {
                return $this->sendError("pay_to", "User not found for " . $payoutAddress->toString());
            }
        }

        $site = Site::fetchOrCreate($user->id, $validated['context']['site']['url']);
        if ($site->status != Site::STATUS_ACTIVE) {
            return $this->sendError("site", "Site '". $site->name ."' is not active");
        }
        $validated['$site'] = $site;

        $zones = [];

        $zoneSizes = Size::findBestFit($validated['width'], $validated['height'], $validated['min_dpi']);


        foreach ($zoneSizes as $zoneSize) {
            $zone = Zone::fetchOrCreate($site->id, $zoneSize, $validated['zone_name']);
            $zones[] = [
                'zone'    => $zone->uuid,
                'options' => [
                    'banner_type' => [
                        $validated['type']
                    ]
                ]
            ];
            $validated['zones'][] = $zone;
        }

        $queryData = [
            'page'      => [
                "iid" => $validated['view_id'],
                "url" => $validated['context']['site']['url'],
            ],
            'zones'     => $zones,
            'zone_mode' => 'best_match'
        ];


        $response = new Response();

        return self::json(
            [
                'banners'   => $this->findBanners($queryData, $request, $response, $contextProvider, $bannerFinder)
                    ->toArray(),
                'zones'     => $queryData['zones'],
                'zoneSizes' => $zoneSizes,
                'success'   => true,
            ]
        );
    }

    private function sendError($type, $message): JsonResponse
    {
        return new JsonResponse(
            [
                'message' => 'The given data was invalid.',
                'errors'  => [
                    $type => $message
                ]
            ],
            422
        );
    }

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
        return self::json($this->findBanners($decodedQueryData, $request, $response, $contextProvider, $bannerFinder));
    }

    public function findSimple(
        Request $request,
        AdUser $contextProvider,
        AdSelect $bannerFinder,
        string $zone_id,
        string $impression_id
    ) {
        $zone = Zone::fetchByPublicId($zone_id);
        $response = new Response();
        $queryData = [
            'page'  => [
                "iid" => $impression_id,
                "url" => $zone->site->url,
            ],
            'zones' => [
                [
                    'zone'    => $zone_id,
                    'options' => [
                        'banner_type' => [
                            $request->get('type')
                        ]
                    ]
                ],
            ],
        ];

        $foundBanner = $this->findBanners($queryData, $request, $response, $contextProvider, $bannerFinder)->first();
        if ($foundBanner) {
            return $this->sendForeignBrandedBanner($foundBanner);
        }
        throw new NotFoundHttpException('Could not find banner');
    }

    private function watermarkImage(\Imagick $im, \Imagick $watermark)
    {
        $w = $im->getImageWidth();
        $h = $im->getImageHeight();

        $box = new \ImagickDraw();
        $box->setFillColor(new \ImagickPixel('white'));
        $box->rectangle($w - 16, 0, $w, 16);
        $im->drawImage($box);
        $im->compositeImage($watermark, \Imagick::COMPOSITE_ATOP, $w - 16, 0);
    }

    private function sendForeignBrandedBanner($foundBanner): \Symfony\Component\HttpFoundation\Response
    {
        $response = new Response();
        $img = Cache::remember(
            'banner_cache.' . $foundBanner['serve_url'],
            (int)(60),
            function () use ($foundBanner) {
                $bannerContent = file_get_contents($foundBanner['serve_url']);
                $hash = sha1($bannerContent);

                if ($hash != $foundBanner['creative_sha1']) {
                    throw new NotFoundHttpException('Content hash mismatch');
                }

                $watermark = new \Imagick(public_path('img/watermark.png'));
                $watermark->resizeImage(16, 16, \Imagick::FILTER_BOX, 0);

                $im = new \Imagick();
                $im->readImageBlob($bannerContent);

                if ($im->getImageFormat() == 'GIF') {
                    $parts = $im->coalesceImages();
                    $i = 0;
                    do {
                        $this->watermarkImage($parts, $watermark);
                    } while ($parts->nextImage());
                    $im = $parts->deconstructImages();
                } else {
                    $this->watermarkImage($im, $watermark);
                }

                return [
                    'data' => $im->getImagesBlob(),
                    'mime' => $im->getImageMimeType()
                ];
            }
        );


        $response->setContent($img['data']);

        $response->headers->set('Content-Type', $img['mime']);

        return $response;
    }

    /**
     * @param array    $decodedQueryData
     * @param Request  $request
     * @param Response $response
     * @param AdUser   $contextProvider
     * @param AdSelect $bannerFinder
     *
     * @return FoundBanners
     */
    private function findBanners(
        array $decodedQueryData,
        Request $request,
        Response $response,
        AdUser $contextProvider,
        AdSelect $bannerFinder
    ): FoundBanners {
        $zones = $decodedQueryData['zones'] ?? [];

        if (!$zones) {
            return new FoundBanners();
        }

        if (($decodedQueryData['zone_mode'] ?? '') !== 'best_match') {
            $zones = array_slice($zones, 0, config('app.max_page_zones'));
        }

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

        $impressionContext = Utils::getPartialImpressionContext($request, $decodedQueryData['page'], $tid);

        $userContext = $contextProvider->getUserContext($impressionContext);

        if ($userContext->isCrawler()) {
            return new FoundBanners();
        }

        if ($userContext->pageRank() <= self::UNACCEPTABLE_PAGE_RANK) {
            if ($userContext->pageRank() == Aduser::CPA_ONLY_PAGE_RANK) {
                foreach ($zones as &$zone) {
                    $zone['options']['cpa_only'] = true;
                }
            } elseif (config('app.env') != 'dev') {
                return new FoundBanners();
            }
        }

        $context = Utils::mergeImpressionContextAndUserContext($impressionContext, $userContext);
        $foundBanners = $bannerFinder->findBanners($zones, $context);

        if (($decodedQueryData['zone_mode'] ?? '') === 'best_match') {
            $foundBanners =
                array_values(
                    $foundBanners->filter(
                        function ($element) {
                            return $element != null;
                        }
                    )->slice(0));
                usort($foundBanners, function($a, $b) {
                   return ($a['rpm'] ?? 9999) - ($b['rpm'] ?? 9999);
                });

                $foundBanners = new FoundBanners(array_slice($foundBanners, 0, 1));
        }

        if (
            $foundBanners->exists(
                function ($key, $element) {
                    return $element != null;
                }
            )
        ) {
            NetworkImpression::register(
                Utils::hexUuidFromBase64UrlWithChecksum($impressionId),
                Utils::hexUuidFromBase64UrlWithChecksum($tid),
                $impressionContext,
                $userContext,
                $foundBanners,
                $zones
            );
        }

        return $foundBanners;
    }

    public function findScript(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        if ($request->query->get('medium') == 'cryptovoxels') {
            return $this->cryptovoxelsScript();
        }
        return $this->webScript();
    }

    public function cryptovoxelsScript(): StreamedResponse
    {
        $params = [
            config('app.main_js_tld') ? ServeDomain::current() : config('app.serve_base_url'),
            '.' . CssUtils::normalizeClass(config('app.adserver_id')),
        ];

        $jsPath = public_path('-/cryptovoxels.js');

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
                'max_age'       => 3600 * 1 * 1,
                's_maxage'      => 3600 * 1 * 1,
                'private'       => false,
                'public'        => true,
            ]
        );

        return $response;
    }

    public function webScript(): StreamedResponse
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
                'max_age'       => 3600 * 24 * 1,
                's_maxage'      => 3600 * 24 * 1,
                'private'       => false,
                'public'        => true,
            ]
        );

        return $response;
    }


    public function logNetworkSimpleClick(Request $request): RedirectResponse
    {
        $impressionId = $request->query->get('iid');
        $networkImpression = NetworkImpression::fetchByImpressionId(
            Utils::hexUuidFromBase64UrlWithChecksum($impressionId)
        );
        if (null === $networkImpression || !$networkImpression->context->banner_id) {
            throw new NotFoundHttpException();
        }

        $clickQuery = parse_query(parse_url($networkImpression->context->click_url, PHP_URL_QUERY));

        $request->query->set('r', $clickQuery['r']);
        $request->query->set(
            'ctx',
            Utils::encodeZones(
                [
                    'page' => [
                        'zone'  => $networkImpression->context->zone_id,
                        'url'   => $networkImpression->context->site->page,
                        'frame' => $networkImpression->context->site->inframe,
                    ]
                ]
            )
        );
        $request->query->set('simple', '1');
        return $this->logNetworkClick($request, $networkImpression->context->banner_id);
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

        $payTo = AdsUtils::normalizeAddress(config('app.adshares_address'));
        try {
            $zoneId = ($networkCase->zone_id ?? null) ?: Utils::getZoneIdFromContext($request->query->get('ctx'));
        } catch (RuntimeException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }
        $publisherId = Zone::fetchPublisherPublicIdByPublicId($zoneId);
        $impressionId = $request->query->get('iid');

        $url = Utils::addUrlParameter($url, 'pto', $payTo);
        $url = Utils::addUrlParameter($url, 'pid', $publisherId);
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

    public function logNetworkSimpleView(Request $request): RedirectResponse
    {
        $impressionId = $request->query->get('iid');
        $networkImpression = NetworkImpression::fetchByImpressionId(
            Utils::hexUuidFromBase64UrlWithChecksum($impressionId)
        );
        if (null === $networkImpression || !$networkImpression->context->banner_id) {
            throw new NotFoundHttpException();
        }

        $viewQuery = parse_query(parse_url($networkImpression->context->view_url, PHP_URL_QUERY));

        $request->query->set('r', $viewQuery['r']);
        $request->query->set(
            'ctx',
            Utils::encodeZones(
                [
                    'page' => [
                        'zone'  => $networkImpression->context->zone_id,
                        'url'   => $networkImpression->context->site->page,
                        'frame' => $networkImpression->context->site->inframe,
                    ]
                ]
            )
        );
        $request->query->set('simple', '1');
        return $this->logNetworkView($request, $networkImpression->context->banner_id);
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
        $payTo = AdsUtils::normalizeAddress(config('app.adshares_address'));

        try {
            $zoneId = ($networkImpression->context->zone_id ?? null)
                ?:
                Utils::getZoneIdFromContext($request->query->get('ctx'));
        } catch (RuntimeException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }
        $publisherId = Zone::fetchPublisherPublicIdByPublicId($zoneId);
        $siteId = Zone::fetchSitePublicIdByPublicId($zoneId);

        $url = Utils::addUrlParameter($url, 'pto', $payTo);
        $url = Utils::addUrlParameter($url, 'pid', $publisherId);

        $response = new RedirectResponse($url);

        if ($request->headers->has('Origin')) {
            $response->headers->set('Access-Control-Allow-Origin', $request->headers->get('Origin'));
        }

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

        $adUserEndpoint = config('app.aduser_serve_subdomain')
            ?
            ServeDomain::current(config('app.aduser_serve_subdomain'))
            :
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
            'url'                   => $banner->serve_url,
            'supplyName'            => config('app.name'),
            'supplyTermsUrl'        => config('app.terms_url'),
            'supplyPrivacyUrl'      => config('app.privacy_url'),
            'supplyPanelUrl'        => config('app.adpanel_url'),
            'supplyBannerReportUrl' => new SecureUrl(
                route(
                    'report-ad',
                    [
                        'banner_id' => $bannerId,
                        'case_id'   => $caseId,
                    ]
                )
            ),
            'supplyBannerRejectUrl' => config('app.adpanel_url') . '/publisher/classifier/' . $bannerId,
            'demand'                => false,
            'bannerType'            => $banner->type,
        ];

        if ($info) {
            $data = array_merge(
                $data,
                [
                    'demand'           => true,
                    'demandName'       => $info->getName(),
                    'demandTermsUrl'   => $info->getTermsUrl() ?? null,
                    'demandPrivacyUrl' => $info->getPrivacyUrl() ?? null,
                    'demandPanelUrl'   => $info->getPanelUrl(),
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
                'key'             => $row->key,
                'occurrences'     => $row->occurrences,
                'cpm_25'          => $row->cpm_25,
                'cpm_50'          => $row->cpm_50,
                'cpm_75'          => $row->cpm_75,
                'negation_cpm_25' => $row->negation_cpm_25,
                'negation_cpm_50' => $row->negation_cpm_50,
                'negation_cpm_75' => $row->negation_cpm_75,
                'data'            => Utils::urlSafeBase64Encode($row->data),
            ];
        }

        return response(
            [
                'meta'       => [
                    'total_events_count' => $meta->total_events_count,
                    'updated_at'         => $meta->updated_at->format(DateTimeInterface::ATOM),
                ],
                'categories' => $result,
            ]
        );
    }
}
