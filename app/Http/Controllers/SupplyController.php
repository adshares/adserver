<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\SitesRejectedDomain;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Rules\PayoutAddressRule;
use Adshares\Adserver\Utilities\AdsAuthenticator;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Adserver\Utilities\CssUtils;
use Adshares\Adserver\Utilities\DomainReader;
use Adshares\Adserver\Utilities\SqlUtils;
use Adshares\Adserver\ViewModel\MediumName;
use Adshares\Adserver\ViewModel\ZoneSize;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Domain\ValueObject\Exception\InvalidUuidException;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Config\UserRole;
use Adshares\Supply\Application\Dto\FoundBanners;
use Adshares\Supply\Application\Service\AdSelect;
use Closure;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use GuzzleHttp\Psr7\Query;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SupplyController extends Controller
{
    private const UNACCEPTABLE_PAGE_RANK = 0.0;
    private const TTL_ONE_HOUR = 3600;

    private static string $adserverId;

    public function __construct()
    {
        self::$adserverId = config('app.adserver_id');
    }

    public function findJson(
        Request $request,
        AdUser $contextProvider,
        AdSelect $bannerFinder,
    ): BaseResponse {
        $type = $request->get('type');
        if (isset($type) && !is_array($type)) {
            $request->offsetSet('type', array($type));
        }

        $validated = $request->validate(
            [
                'pay_to' => ['required', new PayoutAddressRule()],
                'view_id' => ['required', 'regex:#^[A-Za-z0-9+/_-]+[=]{0,3}$#'],
                'zone_name' => ['sometimes', 'regex:/^[0-9a-z -_]+$/i'],
                'width' => ['required', 'numeric', 'gt:0'],
                'height' => ['required', 'numeric', 'gt:0'],
                'depth' => ['sometimes', 'numeric', 'gte:0'],
                'min_dpi' => ['sometimes', 'numeric', 'gt:0'],
                'type' => ['sometimes', 'array'],
                'type.*' => ['string'],
                'mime_type' => ['sometimes', 'array'],
                'mime_type.*' => ['string'],
                'exclude' => ['sometimes', 'array:quality,category'],
                'exclude.*' => ['sometimes', 'array'],
                'context' => ['required', 'array:user,device,site'],
                'context.site.url' => ['required', 'url'],
                'medium' => ['required', 'string'],
                'vendor' => ['nullable', 'string'],
            ]
        );

        $validated['min_dpi'] = $validated['min_dpi'] ?? Zone::DEFAULT_MINIMAL_DPI;
        $validated['zone_name'] = $validated['zone_name'] ?? Zone::DEFAULT_NAME;
        $validated['depth'] = $validated['depth'] ?? Zone::DEFAULT_DEPTH;
        $minDpi = (float)$validated['min_dpi'];

        $context = [
            'iid' => $validated['view_id'],
            'publisher' => $validated['pay_to'],
            'url' => $validated['context']['site']['url'],
            'medium' => $validated['medium'],
            'vendor' => $validated['vendor'] ?? null,
            'metamask' => (bool)($validated['context']['site']['metamask'] ?? 0),
        ];
        if (isset($validated['context']['user']['account'])) {
            $context['uid'] = $validated['context']['user']['account'];
        }
        $placement = [
            'id' => '1',
            'name' => $validated['zone_name'] ?? Zone::DEFAULT_NAME,
            'width' => (int)((float)$validated['width'] * $minDpi),
            'height' => (int)((float)$validated['height'] * $minDpi),
            'depth' => (int)((float)$validated['depth'] ?? Zone::DEFAULT_DEPTH),
        ];
        if (isset($validated['type'])) {
            $placement['types'] = (array)$validated['type'];
        }
        if (isset($validated['mime_type'])) {
            $placement['mimes'] = (array)$validated['mime_type'];
        }
        $input = [
            'context' => $context,
            'placements' => [$placement],
        ];

        if ('POST' === $request->getRealMethod()) {
            $request->merge($input);
        } elseif ('GET' === $request->getRealMethod()) {
            $request->offsetSet('data', Utils::urlSafeBase64Encode(json_encode($input)));
        }

        $response = $this->find($contextProvider, $bannerFinder, $request);
        $content = $response->getContent();
        $banners = json_decode($content, true)['data'];
        if (!empty($banners)) {
            $banners = [self::unmapResult($banners[0])];
        }
        return self::json(
            [
                'banners' => $banners,
                'success' => true,
            ]
        );
    }

    private function sendError($type, $message): JsonResponse
    {
        return new JsonResponse(
            [
                'message' => 'The given data was invalid.',
                'errors' => [
                    $type => $message
                ]
            ],
            BaseResponse::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    public function legacyFind(
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
            throw new UnprocessableEntityHttpException('Data is required');
        }

        if (false !== ($index = strpos($data, '&'))) {
            $data = substr($data, 0, $index);
        }

        try {
            $decodedQueryData = Utils::decodeZones($data);
            if (!isset($decodedQueryData['zones'])) {
                throw new UnprocessableEntityHttpException('Zones are required');
            }
            foreach ($decodedQueryData['zones'] as &$zone) {
                if (isset($zone['pay-to'])) {
                    try {
                        $payoutAddress = WalletAddress::fromString($zone['pay-to']);
                        $user = User::fetchByWalletAddress($payoutAddress);

                        if (!$user) {
                            if (config('app.auto_registration_enabled')) {
                                if (!in_array(UserRole::PUBLISHER, config('app.default_user_roles'))) {
                                    throw new HttpException(Response::HTTP_FORBIDDEN, 'Cannot register publisher');
                                }
                                $user = User::registerWithWallet($payoutAddress, true);
                                if (config('app.auto_confirmation_enabled')) {
                                    $user->confirmAdmin();
                                    $user->saveOrFail();
                                }
                            } else {
                                return $this->sendError("pay_to", "User not found for " . $payoutAddress->toString());
                            }
                        }
                        if (!$user->isPublisher()) {
                            throw new HttpException(Response::HTTP_FORBIDDEN, 'Forbidden');
                        }
                        $site = Site::fetchOrCreate(
                            $user->id,
                            $decodedQueryData['page']['url'],
                            MediumName::Web->value,
                            null
                        );
                        if ($site->status != Site::STATUS_ACTIVE) {
                            return $this->sendError("site", "Site '" . $site->name . "' is not active");
                        }

                        $zoneObject = Zone::fetchOrCreate(
                            $site->id,
                            ZoneSize::fromArray($zone),
                            $zone['zone']
                        );
                        $zone['zone'] = $zoneObject->uuid;
                    } catch (InvalidArgumentException $ex) {
                        return $this->sendError("pay_to", $ex->getMessage());
                    }
                }
            }
        } catch (RuntimeException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }
        return self::json(
            $this->findBanners($decodedQueryData, $request, $response, $contextProvider, $bannerFinder)->toArray()
        );
    }

    public function find(
        AdUser $contextProvider,
        AdSelect $bannerFinder,
        Request $request,
    ): BaseResponse {
        $response = new Response();

        if ($request->headers->has('Origin')) {
            $response->headers->set('Access-Control-Allow-Origin', $request->headers->get('Origin'));
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        }

        if ('POST' === $request->getRealMethod()) {
            $input = $request->input();
        } elseif ('GET' === $request->getRealMethod()) {
            if (null === ($query = $request->query('data'))) {
                throw new UnprocessableEntityHttpException('Query `data` is required');
            }
            $input = json_decode(Utils::urlSafeBase64Decode($query), true);
        } else {
            throw new MethodNotAllowedHttpException(['GET', 'POST']);
        }

        if (!is_array($input)) {
            throw new UnprocessableEntityHttpException('Invalid body type');
        }
        foreach (['context', 'placements'] as $field) {
            if (!isset($input[$field])) {
                throw new UnprocessableEntityHttpException(sprintf('Field `%s` is required', $field));
            }
        }

        if (!is_array($input['context'])) {
            throw new UnprocessableEntityHttpException('Field `context` must be an object');
        }
        $context = $input['context'];
        foreach (['iid', 'url'] as $field) {
            if (!isset($context[$field])) {
                throw new UnprocessableEntityHttpException(sprintf('Field `context.%s` is required', $field));
            }
            if (!is_string($context[$field])) {
                throw new UnprocessableEntityHttpException(sprintf('Field `context.%s` must be a string', $field));
            }
        }
        if (array_key_exists('metamask', $context) && !is_bool($context['metamask'])) {
            throw new UnprocessableEntityHttpException('Field `context.metamask` must be a boolean');
        }
        if (array_key_exists('uid', $context) && !is_string($context['uid'])) {
            throw new UnprocessableEntityHttpException('Field `context.uid` must be a string');
        }
        if (!is_array($input['placements'])) {
            throw new UnprocessableEntityHttpException('Field `placements` must be an array');
        }

        $isDynamicFind = array_key_exists('publisher', $context);
        if ($isDynamicFind) {
            if (!is_string($context['publisher'])) {
                throw new UnprocessableEntityHttpException('Field `context.publisher` must be a string');
            }
            if (!isset($context['medium'])) {
                throw new UnprocessableEntityHttpException('Field `context.medium` is required');
            }
            if (!is_string($context['medium'])) {
                throw new UnprocessableEntityHttpException('Field `context.medium` must be a string');
            }
            if (isset($context['vendor']) && !is_string($context['vendor'])) {
                throw new UnprocessableEntityHttpException('Field `context.vendor` must be a string');
            }

            foreach ($input['placements'] as $placement) {
                if (!is_array($placement)) {
                    throw new UnprocessableEntityHttpException('Field `placements[]` must be an object');
                }
                self::validatePlacementCommonFields($placement);
                self::validatePlacementFieldsDynamic($placement);
            }
        } else {
            foreach ($input['placements'] as $placement) {
                if (!is_array($placement)) {
                    throw new UnprocessableEntityHttpException('Field `placements[]` must be an object');
                }
                self::validatePlacementCommonFields($placement);
                self::validatePlacementFields($placement);
            }
        }

        if ($isDynamicFind) {
            $site = $this->getSiteOrFail($context);
            foreach ($input['placements'] as $key => $placement) {
                $zoneType = $this->getZoneType($placement);
                try {
                    $zoneObject = Zone::fetchOrCreate(
                        $site->id,
                        ZoneSize::fromArray($placement),
                        $placement['name'] ?? Zone::DEFAULT_NAME,
                        $zoneType,
                    );
                } catch (InvalidArgumentException $exception) {
                    throw new UnprocessableEntityHttpException($exception->getMessage());
                }
                $input['placements'][$key]['placementId'] = $zoneObject->uuid;
            }
        }

        $mappedInput = self::mapFindInput($input);
        $foundBanners = $this->findBanners($mappedInput, $request, $response, $contextProvider, $bannerFinder)
            ->filter(fn($banner) => null !== $banner)
            ->map($this->mapFoundBannerToResult())
            ->getValues();

        $data = ['data' => $foundBanners];
        if ($custom = $this->getCustomData($context, $foundBanners)) {
            $data['custom'] = $custom;
        }
        return self::json($data);
    }

    private function getCustomData(array $context, array $banners): array
    {
        return [];
    }

    private function checkDecodedQueryData(array $decodedQueryData): void
    {
        if ($this->isSiteRejected($decodedQueryData['page']['url'] ?? '')) {
            throw new BadRequestHttpException('Site rejected');
        }
        if (!config('app.allow_zone_in_iframe') && $this->isAnyZoneInFrame($decodedQueryData)) {
            throw new BadRequestHttpException('Cannot run in iframe');
        }
        if (
            ($decodedQueryData['page']['pop'] ?? false)
            && DomainReader::domain($decodedQueryData['page']['ref'] ?? '')
            != DomainReader::domain($decodedQueryData['page']['url'] ?? '')
        ) {
            throw new BadRequestHttpException('Bad request.');
        }
    }

    private function isAnyZoneInFrame(array $decodedQueryData): bool
    {
        if ($decodedQueryData['page']['frame'] ?? false) {
            // legacy code, should be deleted when legacyFind will be removed
            return true;
        }
        if (isset($decodedQueryData['placements'])) {
            foreach ($decodedQueryData['placements'] as $placement) {
                if (!($placement['options']['topframe'] ?? true)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function extractZones(array $decodedQueryData): array
    {
        $zones = $decodedQueryData['placements'] ?? $decodedQueryData['zones'] ?? [];// Key 'zones' is for legacy search
        if (!$zones) {
            throw new BadRequestHttpException('No placements');
        }
        return array_slice($zones, 0, config('app.max_page_zones'));
    }

    /**
     * @param array $decodedQueryData
     * @param Request $request
     * @param Response $response
     * @param AdUser $contextProvider
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
        $this->checkDecodedQueryData($decodedQueryData);
        $impressionId = self::impressionIdToUuid($decodedQueryData['page']['iid']);

        $tid = Utils::attachOrProlongTrackingCookie(
            $request,
            $response,
            '',
            new DateTime(),
            $impressionId
        );

        if (isset($decodedQueryData['user'])) {
            $decodedQueryData['user']['tid'] = $tid;
        } else {
            $decodedQueryData['user'] = ['tid' => $tid];
        }
        $impressionContext = Utils::getPartialImpressionContext(
            $request,
            $decodedQueryData['page'],
            $decodedQueryData['user']
        );
        $userContext = $contextProvider->getUserContext($impressionContext);

        if ($userContext->isCrawler()) {
            throw new AccessDeniedHttpException('Crawlers are not allowed');
        }

        $zones = $this->extractZones($decodedQueryData);
        if ($userContext->pageRank() <= self::UNACCEPTABLE_PAGE_RANK) {
            if ($userContext->pageRank() == Aduser::CPA_ONLY_PAGE_RANK) {
                foreach ($zones as &$zone) {
                    $zone['options']['cpa_only'] = true;
                }
            } else {
                throw new AccessDeniedHttpException('This site is banned');
            }
        }

        $context = Utils::mergeImpressionContextAndUserContext($impressionContext, $userContext);
        $foundBanners = $bannerFinder->findBanners($zones, $context, $impressionId);

        if ($foundBanners->exists(fn($key, $element) => $element != null)) {
            NetworkImpression::register(
                $impressionId,
                Utils::hexUuidFromBase64UrlWithChecksum($tid),
                $impressionContext,
                $userContext,
                $foundBanners,
            );
        }

        return $foundBanners;
    }

    public function findScript(Request $request): BaseResponse
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
            '.' . CssUtils::normalizeClass(self::$adserverId),
            config('app.version')
        ];

        $jsPath = public_path('-/cryptovoxels.js');

        $response = new StreamedResponse();
        $response->setCallback(
            static function () use ($jsPath, $params) {
                echo str_replace(
                    [
                        '{{ ORIGIN }}',
                        '{{ SELECTOR }}',
                        '{{ VERSION }}'
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
                'max_age' => self::TTL_ONE_HOUR,
                's_maxage' => self::TTL_ONE_HOUR,
                'private' => false,
                'public' => true,
            ]
        );

        return $response;
    }

    public function webScript(): StreamedResponse
    {
        $params = [
            config('app.main_js_tld') ? ServeDomain::current() : config('app.serve_base_url'),
            '.' . CssUtils::normalizeClass(self::$adserverId),
            config('app.banner_rotate_interval'),
        ];

        $jsPath = public_path('-/find.js');

        $response = new StreamedResponse();
        $response->setCallback(
            static function () use ($jsPath, $params) {
                echo str_replace(
                    [
                        '{{ ORIGIN }}',
                        '{{ SELECTOR }}',
                        '{{ ROTATE_INTERVAL }}',
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
                'max_age' => self::TTL_ONE_HOUR * 24,
                's_maxage' => self::TTL_ONE_HOUR * 24,
                'private' => false,
                'public' => true,
            ]
        );

        return $response;
    }

    public function logNetworkSimpleClick(Request $request): RedirectResponse
    {
        $impressionId = self::impressionIdToUuid($request->query->get('iid'));
        $networkImpression = NetworkImpression::fetchByImpressionId($impressionId);
        if (null === $networkImpression || !$networkImpression->context->banner_id) {
            throw new NotFoundHttpException();
        }

        $clickQuery = Query::parse(parse_url($networkImpression->context->click_url, PHP_URL_QUERY));

        $request->query->set('r', $clickQuery['r']);
        $request->query->set(
            'ctx',
            Utils::encodeZones(
                [
                    'page' => [
                        'zone' => $networkImpression->context->zone_id,
                        'url' => $networkImpression->context->site->page,
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

        $caseId = str_replace('-', '', $request->query->get('cid'));
        if (null === ($networkCase = NetworkCase::fetchByCaseId($caseId))) {
            throw new NotFoundHttpException();
        }

        $payTo = AdsUtils::normalizeAddress(config('app.adshares_address'));
        if (null === ($zoneId = ($networkCase->zone_id ?? null))) {
            try {
                $zoneId = null !== $request->query->get('zid')
                    ? str_replace('-', '', $request->query->get('zid'))
                    : Utils::getZoneIdFromContext($request->query->get('ctx'));
            } catch (RuntimeException $exception) {
                throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
            }
        }
        $publisherId = Zone::fetchPublisherPublicIdByPublicId($zoneId);
        $impressionId = $request->query->get('iid');

        $url = Utils::addUrlParameter($url, 'pto', $payTo);
        $url = Utils::addUrlParameter($url, 'pid', $publisherId);
        $url = Utils::addUrlParameter($url, 'iid', $impressionId);
        if (!$request->query->has('ctx')) {
            $ctx = $this->buildCtx($networkCase->networkImpression, $impressionId, $zoneId);
            $url = Utils::addUrlParameter($url, 'ctx', $ctx);
        }

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
        $impressionId = self::impressionIdToUuid($request->query->get('iid'));
        $networkImpression = NetworkImpression::fetchByImpressionId($impressionId);
        if (null === $networkImpression || !$networkImpression->context->banner_id) {
            throw new NotFoundHttpException();
        }

        $viewQuery = Query::parse(parse_url($networkImpression->context->view_url, PHP_URL_QUERY));

        $request->query->set('r', $viewQuery['r']);
        $request->query->set(
            'ctx',
            Utils::encodeZones(
                [
                    'page' => [
                        'zone' => $networkImpression->context->zone_id,
                        'url' => $networkImpression->context->site->page,
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

        if (null === ($iid = $request->query->get('iid'))) {
            throw new BadRequestHttpException('Invalid parameters.');
        }
        $impressionId = self::impressionIdToUuid($iid);
        $networkImpression = NetworkImpression::fetchByImpressionId($impressionId);
        if (null === $networkImpression) {
            throw new NotFoundHttpException();
        }

        $url = $this->getRedirectionUrlFromQuery($request);
        if ($url) {
            $url = $this->addQueryStringToUrl($request, $url);
        }

        $payTo = AdsUtils::normalizeAddress(config('app.adshares_address'));

        if (null === ($zoneId = ($networkImpression->context->zone_id ?? null))) {
            try {
                $zoneId = null !== $request->query->get('zid')
                    ? str_replace('-', '', $request->query->get('zid'))
                    : Utils::getZoneIdFromContext($request->query->get('ctx'));
            } catch (RuntimeException $exception) {
                throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
            }
        }
        $publisherId = Zone::fetchPublisherPublicIdByPublicId($zoneId);
        $siteId = Zone::fetchSitePublicIdByPublicId($zoneId);

        $url = Utils::addUrlParameter($url, 'pto', $payTo);
        $url = Utils::addUrlParameter($url, 'pid', $publisherId);
        if (!$request->query->has('ctx')) {
            $ctx = $this->buildCtx($networkImpression, $impressionId, $zoneId);
            $url = Utils::addUrlParameter($url, 'ctx', $ctx);
        }
        $response = new RedirectResponse($url);

        if ($request->headers->has('Origin')) {
            $response->headers->set('Access-Control-Allow-Origin', $request->headers->get('Origin'));
        }

        $response->send();

        $caseId = str_replace('-', '', $request->query->get('cid'));
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
            || !($request->query->has('ctx') || (Uuid::isValid($request->query->get('zid', ''))))
            || !Uuid::isValid($request->query->get('cid', ''))
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
        $impressionId = self::impressionIdToUuid($request->query->get('iid', ''));

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
                urlencode(self::$adserverId),
                $trackingId,
                $impressionId
            );

            $response->setStatusCode(BaseResponse::HTTP_FOUND);
            $response->headers->set('Location', new SecureUrl($adUserUrl));
        }

        return $response;
    }

    public function why(Request $request): View
    {
        try {
            $bannerId = (new Uuid($request->query->get('bid', '')))->hex();
            $caseId = (new Uuid($request->query->get('cid', '')))->hex();
        } catch (InvalidUuidException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }

        if (null === ($banner = NetworkBanner::fetchByPublicId($bannerId))) {
            throw new NotFoundHttpException('No matching banner');
        }
        $campaign = $banner->campaign()->first();
        $networkHost = NetworkHost::fetchByHost($campaign->source_host);

        $info = $networkHost->info ?? null;

        $data = [
            'url' => $banner->serve_url,
            'source' => strtolower(preg_replace('/\s/', '-', config('app.adserver_name'))),
            'supplyName' => config('app.adserver_name'),
            'supplyTermsUrl' => route('terms-url'),
            'supplyPrivacyUrl' => route('privacy-url'),
            'supplyLandingUrl' => config('app.landing_url'),
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
            'bannerSize' => $banner->size,
            'bannerType' => $banner->type,
        ];

        if ($info) {
            $data = array_merge(
                $data,
                [
                    'demand' => true,
                    'demandName' => $info->getName(),
                    'demandTermsUrl' => $info->getTermsUrl(),
                    'demandPrivacyUrl' => $info->getPrivacyUrl(),
                    'demandLandingUrl' => $info->getLandingUrl(),
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
        try {
            $bannerId = (new Uuid($bannerId))->hex();
            $caseId = (new Uuid($caseId))->hex();
        } catch (InvalidUuidException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }

        if (null === ($case = NetworkCase::fetchByCaseId($caseId))) {
            throw new NotFoundHttpException('Missing case');
        }

        if ($case->banner_id !== $bannerId) {
            throw new UnprocessableEntityHttpException('Wrong banner id');
        }

        $userId = User::fetchByUuid($case->publisher_id)->id;

        Storage::disk('local')->append(
            'reported-ads.txt',
            sprintf('%s;%s', $userId, $bannerId)
        );

        return 'Thank you for reporting ad.';
    }

    private function isSiteRejected(string $url): bool
    {
        $rejectedDomain = SitesRejectedDomain::getMatchingRejectedDomain(DomainReader::domain($url));
        $isDomainRejected = null !== $rejectedDomain;
        if ($isDomainRejected) {
            Site::rejectByDomains([$rejectedDomain]);
        }
        return $isDomainRejected;
    }

    public function targetingReachList(AdsAuthenticator $authenticator, Request $request): JsonResponse
    {
        $whitelist = config('app.inventory_export_whitelist');
        if (!empty($whitelist)) {
            $account = $authenticator->verifyRequest($request);
            if (!in_array($account, $whitelist)) {
                throw new AccessDeniedHttpException();
            }
        }

        if (null === ($networkHost = NetworkHost::fetchByAddress(config('app.adshares_address')))) {
            Log::error('[Supply Targeting Reach] Cannot get adserver ID');
            return self::targetingReachResponse();
        }

        if (null === ($meta = NetworkVectorsMeta::fetchByNetworkHostId($networkHost->id))) {
            Log::error('[Supply Targeting Reach] Cannot get adserver meta');
            return self::targetingReachResponse();
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

        return self::targetingReachResponse($meta->total_events_count, $meta->updated_at, $result);
    }

    private static function targetingReachResponse(
        int $eventsCount = 0,
        ?DateTimeInterface $updateDateTime = null,
        array $categories = [],
    ): JsonResponse {
        return self::json(
            [
                'meta' => [
                    'total_events_count' => $eventsCount,
                    'updated_at' => ($updateDateTime ?: new DateTimeImmutable())->format(DateTimeInterface::ATOM),
                ],
                'categories' => $categories,
            ],
        );
    }

    private function getPublisherOrFail(string $publisher): User
    {
        if (Uuid::isValid($publisher)) {
            $user = User::fetchByUuid(str_replace('-', '', $publisher));
        } else {
            try {
                $payoutAddress = WalletAddress::fromString($publisher);
            } catch (InvalidArgumentException) {
                throw new UnprocessableEntityHttpException(
                    'Field `context.publisher` must be an ID or account address'
                );
            }
            $user = User::fetchByWalletAddress($payoutAddress);
            if (null === $user && config('app.auto_registration_enabled')) {
                if (!in_array(UserRole::PUBLISHER, config('app.default_user_roles'))) {
                    throw new HttpException(BaseResponse::HTTP_FORBIDDEN, 'Cannot register publisher');
                }
                $user = User::registerWithWallet($payoutAddress, true);
                if (config('app.auto_confirmation_enabled')) {
                    $user->confirmAdmin();
                    $user->saveOrFail();
                }
            }
        }

        if (null === $user) {
            throw new UnprocessableEntityHttpException(
                sprintf('User not found for %s', $publisher)
            );
        }

        if (!$user->isPublisher()) {
            throw new HttpException(BaseResponse::HTTP_FORBIDDEN, 'Forbidden');
        }

        return $user;
    }

    private function getSiteOrFail(array $context): Site
    {
        $user = $this->getPublisherOrFail($context['publisher']);

        try {
            $site = Site::fetchOrCreate(
                $user->id,
                $context['url'],
                $context['medium'],
                $context['vendor'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage());
        }

        if (Site::STATUS_ACTIVE !== $site->status) {
            throw new UnprocessableEntityHttpException(sprintf('Site `%s` is not active', $site->name));
        }

        return $site;
    }

    private function getZoneType(array $placement): ?string
    {
        if (isset($placement['types'])) {
            $zoneTypes = array_values(
                array_unique(
                    array_map(fn($type) => Utils::getZoneTypeByBannerType($type), $placement['types'])
                )
            );
            if (count($zoneTypes) > 1) {
                throw new UnprocessableEntityHttpException(
                    'Field `placements[].types` cannot contain conflicting types'
                );
            }
            if (Zone::TYPE_DISPLAY !== $zoneTypes[0]) {
                throw new UnprocessableEntityHttpException(
                    'Field `placements[].types` cannot contain non-displayable type'
                );
            }
        }
        return Zone::TYPE_DISPLAY;
    }

    private static function mapFindInput(array $input): array
    {
        $context = $input['context'];
        $mapped = [
            'page' => [
                'iid' => $context['iid'],
                'url' => $context['url'],
            ],
        ];
        if (isset($context['metamask'])) {
            $mapped['page']['metamask'] = (int)($context['metamask']);
        }
        if (isset($context['uid'])) {
            $mapped['user']['account'] = $context['uid'];
        }

        foreach ($input['placements'] as $placement) {
            $options = [
                'banner_type' => $placement['types'] ?? null,
                'banner_mime' => $placement['mimes'] ?? null,
            ];
            if (isset($placement['topframe'])) {
                $options['topframe'] = $placement['topframe'];
            }
            $mapped['placements'][] = [
                'id' => $placement['id'],
                'placementId' => $placement['placementId'],
                'options' => $options,
            ];
        }

        return $mapped;
    }

    private function mapFoundBannerToResult(): Closure
    {
        return function ($item) {
            return [
                'id' => $item['request_id'],
                'creativeId' => $item['id'],
                'placementId' => $item['zone_id'],
                // Field zoneId is deprecated, use placementId instead
                'zoneId' => $item['zone_id'],
                'publisherId' => $item['publisher_id'],
                'demandServer' => $item['pay_from'],
                'supplyServer' => $item['pay_to'],
                'type' => $item['type'],
                'scope' => $item['size'],
                'hash' => $item['creative_sha1'],
                'serveUrl' => $item['serve_url'],
                'viewUrl' => $item['view_url'],
                'clickUrl' => $item['click_url'],
                'infoBox' => $item['info_box'],
                'rpm' => $item['rpm'],
            ];
        };
    }

    private static function unmapResult(array $item): array
    {
        return [
            'request_id' => $item['id'],
            'id' => $item['creativeId'],
            'zone_id' => $item['placementId'],
            'publisher_id' => $item['publisherId'],
            'pay_from' => $item['demandServer'],
            'pay_to' => $item['supplyServer'],
            'type' => $item['type'],
            'size' => $item['scope'],
            'creative_sha1' => $item['hash'],
            'serve_url' => $item['serveUrl'],
            'view_url' => $item['viewUrl'],
            'click_url' => $item['clickUrl'],
            'info_box' => $item['infoBox'],
            'rpm' => $item['rpm'],
        ];
    }

    private static function validatePlacementCommonFields(array $placement): void
    {
        if (!isset($placement['id'])) {
            throw new UnprocessableEntityHttpException('Field `placements[].id` is required');
        }
        if (!is_string($placement['id'])) {
            throw new UnprocessableEntityHttpException('Field `placements[].id` must be a string');
        }
        $fieldsOptional = [
            'types',
            'mimes',
        ];
        foreach ($fieldsOptional as $field) {
            if (isset($placement[$field])) {
                if (!is_array($placement[$field])) {
                    throw new UnprocessableEntityHttpException(
                        sprintf('Field `placements[].%s` must be an array', $field)
                    );
                }
                if (empty($placement[$field])) {
                    throw new UnprocessableEntityHttpException(
                        sprintf('Field `placements[].%s` must be a non-empty array', $field)
                    );
                }
                foreach ($placement[$field] as $entry) {
                    if (!is_string($entry)) {
                        throw new UnprocessableEntityHttpException(
                            sprintf('Field `placements[].%s` must be an array of strings', $field)
                        );
                    }
                }
            }
        }

        if (array_key_exists('topframe', $placement) && !is_bool($placement['topframe'])) {
            throw new UnprocessableEntityHttpException('Field `placements[].topframe` must be a boolean');
        }
    }

    private static function validatePlacementFields(array $placement): void
    {
        if (!isset($placement['placementId'])) {
            throw new UnprocessableEntityHttpException('Field `placements[].placementId` is required');
        }
        if (!Utils::isUuidValid($placement['placementId'])) {
            throw new UnprocessableEntityHttpException(
                'Field `placements[].placementId` is must be a hexadecimal string of length 32'
            );
        }
    }

    private static function validatePlacementFieldsDynamic(array $placement): void
    {
        $fieldsRequired = [
            'width',
            'height',
        ];
        foreach ($fieldsRequired as $field) {
            if (!isset($placement[$field])) {
                throw new UnprocessableEntityHttpException(
                    sprintf('Field `placements[].%s` is required', $field)
                );
            }
            if (!is_numeric($placement[$field])) {
                throw new UnprocessableEntityHttpException(
                    sprintf('Field `placements[].%s` must be a number', $field)
                );
            }
        }
        $fieldsOptional = [
            'depth',
            'minDpi',
        ];
        foreach ($fieldsOptional as $field) {
            if (array_key_exists($field, $placement) && !is_numeric($placement[$field])) {
                throw new UnprocessableEntityHttpException(
                    sprintf('Field `placements[].%s` must be a number', $field)
                );
            }
        }

        $field = 'name';
        if (array_key_exists($field, $placement) && !is_string($placement[$field])) {
            throw new UnprocessableEntityHttpException(
                sprintf('Field `placements[].%s` must be a string', $field)
            );
        }
    }

    private static function impressionIdToUuid(string $impressionId): string
    {
        if (Uuid::isValid($impressionId)) {
            return str_replace('-', '', $impressionId);
        }
        return Utils::hexUuidFromBase64UrlWithChecksum($impressionId);
    }

    private function buildCtx(
        NetworkImpression $networkImpression,
        string $impressionId,
        string $zoneId,
    ): string {
        $impressionContext = $networkImpression->context;
        $ctx = [
            'page' => [
                'iid' => $impressionId,
                'keywords' => join(',', $impressionContext->site->keywords ?? []),
                'metamask' => $impressionContext->device->extensions->metamask ?? 0,
                'options' => '',
                'pop' => $impressionContext->site->popup ?? 0,
                'ref' => $impressionContext->site->referrer ?? '',
                'url' => $impressionContext->site->page ?? '',
                'zone' => $zoneId,
            ]
        ];
        if (null !== ($inframe = $impressionContext->site->inframe)) {
            $ctx['page']['frame'] = 'yes' === $inframe ? 1 : 0;
        }
        if (null !== ($account = $impressionContext->user->account ?? null)) {
            $ctx['user']['account'] = $account;
        }
        return Utils::UrlSafeBase64Encode(json_encode($ctx));
    }
}
