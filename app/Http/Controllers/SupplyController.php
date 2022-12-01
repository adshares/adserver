<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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
use Adshares\Adserver\Models\SupplyBlacklistedDomain;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Rules\PayoutAddressRule;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Adserver\Utilities\CssUtils;
use Adshares\Adserver\Utilities\DomainReader;
use Adshares\Adserver\Utilities\SqlUtils;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Config\UserRole;
use Adshares\Supply\Application\Dto\FoundBanners;
use Adshares\Supply\Application\Service\AdSelect;
use Adshares\Supply\Domain\ValueObject\Size;
use Closure;
use DateTime;
use DateTimeInterface;
use Exception;
use GuzzleHttp\Psr7\Query;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class SupplyController extends Controller
{
    private const UNACCEPTABLE_PAGE_RANK = 0.0;
    private const TTL_ONE_HOUR = 3600;
    private const ZONE_DEPTH_DEFAULT = 0;
    private const ZONE_MINIMAL_DPI_DEFAULT = 1;
    private const ZONE_NAME_DEFAULT = 'default';

    private static string $adserverId;

    public function __construct()
    {
        self::$adserverId = config('app.adserver_id');
    }

    public function findJson(
        Request $request,
        AdUser $contextProvider,
        AdSelect $bannerFinder,
        ConfigurationRepository $configurationRepository
    ) {
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

        $validated['min_dpi'] = $validated['min_dpi'] ?? self::ZONE_MINIMAL_DPI_DEFAULT;
        $validated['zone_name'] = $validated['zone_name'] ?? self::ZONE_NAME_DEFAULT;
        $validated['depth'] = $validated['depth'] ?? self::ZONE_DEPTH_DEFAULT;

        $payoutAddress = WalletAddress::fromString($validated['pay_to']);
        $user = User::fetchByWalletAddress($payoutAddress);

        if (!$user) {
            if (config('app.auto_registration_enabled')) {
                if (!in_array(UserRole::PUBLISHER, config('app.default_user_roles'))) {
                    throw new HttpException(Response::HTTP_FORBIDDEN, 'Cannot register publisher');
                }
                $user = User::registerWithWallet($payoutAddress, true);
            } else {
                return $this->sendError("pay_to", "User not found for " . $payoutAddress->toString());
            }
        }
        if (!$user->isPublisher()) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'Forbidden');
        }
        $site = Site::fetchOrCreate(
            $user->id,
            $validated['context']['site']['url'],
            $validated['medium'],
            $validated['vendor'] ?? null
        );
        if ($site->status != Site::STATUS_ACTIVE) {
            return $this->sendError("site", "Site '" . $site->name . "' is not active");
        }

        $medium = $configurationRepository->fetchMedium($validated['medium'], $validated['vendor']);
        $zones = [];

        $zoneSizes = Size::findBestFit(
            $medium,
            (float)$validated['width'],
            (float)$validated['height'],
            (float)$validated['depth'],
            (float)$validated['min_dpi']
        );

        foreach ($zoneSizes as $zoneSize) {
            $zone = Zone::fetchOrCreate($site->id, $zoneSize, $validated['zone_name']);
            $zones[] = [
                'zone' => $zone->uuid,
                'options' => [
                    'banner_type' => isset($validated['type']) ? ((array)$validated['type']) : null,
                    'banner_mime' => $validated['mime_type'] ?? null
                ]
            ];
        }

        $queryData = [
            'page' => [
                'iid' => $validated['view_id'],
                'url' => $validated['context']['site']['url'],
                'metamask' => $validated['context']['site']['metamask'] ?? 0,
            ],
            'user' => $validated['context']['user'] ?? [],
            'zones' => $zones,
            'zone_mode' => 'best_match'
        ];


        $response = new Response();

        return self::json(
            [
                'banners' => $this->findBanners($queryData, $request, $response, $contextProvider, $bannerFinder)
                    ->toArray(),
                'zones' => $queryData['zones'],
                'zoneSizes' => $zoneSizes,
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
            Response::HTTP_UNPROCESSABLE_ENTITY
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
                            'website',
                            null
                        );
                        if ($site->status != Site::STATUS_ACTIVE) {
                            return $this->sendError("site", "Site '" . $site->name . "' is not active");
                        }

                        $zoneObject = Zone::fetchOrCreate(
                            $site->id,
                            "{$zone['width']}x{$zone['height']}",
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
        ConfigurationRepository $configurationRepository,
        Request $request,
    ) {
        $response = new Response();

        if ($request->headers->has('Origin')) {
            $response->headers->set('Access-Control-Allow-Origin', $request->headers->get('Origin'));
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        }

        if ('POST' === $request->getRealMethod()) {
            $input = $request->input();
        } elseif ('OPTIONS' === $request->getRealMethod()) {
            $response->setStatusCode(Response::HTTP_NO_CONTENT);
            $response->headers->set('Access-Control-Max-Age', 1728000);
            return $response;
        } else {
            throw new MethodNotAllowedHttpException(['POST']);
        }

        if (!is_array($input)) {
            throw new UnprocessableEntityHttpException('Invalid body type');
        }
        foreach (['page', 'zones'] as $field) {
            if (!isset($input[$field])) {
                throw new UnprocessableEntityHttpException(sprintf('Field `%s` is required', $field));
            }
        }

        if (!is_array($input['page'])) {
            throw new UnprocessableEntityHttpException('Field `page` must be an object');
        }
        $page = $input['page'];
        foreach (['iid', 'url'] as $field) {
            if (!isset($page[$field])) {
                throw new UnprocessableEntityHttpException(sprintf('Field `page.%s` is required', $field));
            }
            if (!is_string($page[$field])) {
                throw new UnprocessableEntityHttpException(sprintf('Field `page.%s` must be a string', $field));
            }
        }
        if (!is_array($input['zones'])) {
            throw new UnprocessableEntityHttpException('Field `zones` must be an array');
        }
        foreach ($input['zones'] as $zone) {
            $fieldsOptional = [
                'types',
                'mimeTypes',
            ];
            foreach ($fieldsOptional as $field) {
                if (isset($zone[$field])) {
                    if (!is_string($zone[$field])) {
                        throw new UnprocessableEntityHttpException(
                            sprintf('Field `zones.*.%s` must be a string', $field)
                        );
                    }
                }
            }
        }

        $isDynamicFind = array_key_exists('publisher', $page);
        if ($isDynamicFind) {
            if (!isset($page['medium'])) {
                throw new UnprocessableEntityHttpException('Field `page.medium` is required');
            }
            if (!is_string($page['medium'])) {
                throw new UnprocessableEntityHttpException('Field `page.medium` must be a string');
            }
            if (isset($page['vendor'])) {
                if (!is_string($page['vendor'])) {
                    throw new UnprocessableEntityHttpException('Field `page.vendor` must be a string');
                }
            }
            try {
                $medium = $configurationRepository->fetchMedium(
                    $page['medium'],
                    $page['vendor'] ?? null
                );
            } catch (InvalidArgumentException $exception) {
                throw new UnprocessableEntityHttpException($exception->getMessage());
            }
            if (!is_string($page['publisher'])) {
                throw new UnprocessableEntityHttpException('Field `page.publisher` must be a string');
            }
            $user = $this->getPublisherOrFail($page['publisher']);
            foreach ($input['zones'] as $key => $zone) {
                $fieldsRequired = [
                    'width',
                    'height',
                ];
                foreach ($fieldsRequired as $field) {
                    if (!isset($zone[$field])) {
                        throw new UnprocessableEntityHttpException(sprintf('Field `zones.*.%s` is required', $field));
                    }
                    if (!is_string($zone[$field])) {
                        throw new UnprocessableEntityHttpException(
                            sprintf('Field `zones.*.%s` must be a string', $field)
                        );
                    }
                }
                $fieldsOptional = [
                    'depth',
                    'minDpi',
                ];
                foreach ($fieldsOptional as $field) {
                    if (isset($zone[$field])) {
                        if (!is_string($zone[$field])) {
                            throw new UnprocessableEntityHttpException(
                                sprintf('Field `zones.*.%s` must be a string', $field)
                            );
                        }
                    }
                }

                try {
                    $site = Site::fetchOrCreate(
                        $user->id,
                        $page['url'],
                        $page['medium'],
                        $page['vendor'] ?? null,
                    );
                } catch (InvalidArgumentException $exception) {
                    throw new UnprocessableEntityHttpException($exception->getMessage());
                }

                if (Site::STATUS_ACTIVE !== $site->status) {
                    throw new UnprocessableEntityHttpException(sprintf("Site '%s' is not active", $site->name));
                }

                $zoneSizes = Size::findBestFit(
                    $medium,
                    (float)$zone['width'],
                    (float)$zone['height'],
                    (float)($zone['depth'] ?? self::ZONE_DEPTH_DEFAULT),
                    (float)($zone['min_dpi'] ?? self::ZONE_MINIMAL_DPI_DEFAULT),
                );
                $zoneObject = Zone::fetchOrCreate(
                    $site->id,
                    $zoneSizes[0],
                    $zone['name'] ?? self::ZONE_NAME_DEFAULT,
                );
                $input['zones'][$key]['zoneId'] = $zoneObject->uuid;
            }
        } else {
            foreach ($input['zones'] as $zone) {
                if (!isset($zone['zoneId'])) {
                    throw new UnprocessableEntityHttpException('Field `zone.*.zoneId` is required');
                }
                if (!Utils::isUuidValid($zone['zoneId'])) {
                    throw new UnprocessableEntityHttpException(
                        'Field `zone.*.zoneId` is must be a hexadecimal string of length 32'
                    );
                }
            }
        }

        $foundBanners = $this->findBanners($input, $request, $response, $contextProvider, $bannerFinder);
        return self::json($foundBanners->map($this->mapFoundBannerToResult()));
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
            'page' => [
                "iid" => $impression_id,
                "url" => $zone->site->url,
            ],
            'zones' => [
                [
                    'zone' => $zone_id,
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
            self::TTL_ONE_HOUR,
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

    private function checkDecodedQueryData(array $decodedQueryData): void
    {
        if ($this->isPageBlacklisted($decodedQueryData['page']['url'] ?? '')) {
            throw new BadRequestHttpException('Site not accepted');
        }
        if (!config('app.allow_zone_in_iframe') && ($decodedQueryData['page']['frame'] ?? false)) {
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

    private function decodeZones(array $decodedQueryData): array
    {
        $zones = $decodedQueryData['zones'] ?? [];
        if (!$zones) {
            throw new BadRequestHttpException('Site not accepted');
        }
        if (($decodedQueryData['zone_mode'] ?? '') !== 'best_match') {
            $zones = array_slice($zones, 0, config('app.max_page_zones'));
        }
        return $zones;
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
        $impressionId = $decodedQueryData['page']['iid'];

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
            return new FoundBanners();
        }

        $zones = $this->decodeZones($decodedQueryData);
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
            $values = $foundBanners->filter(fn($element) => $element != null)->getValues();
            shuffle($values);
            $foundBanners = new FoundBanners(array_slice($values, 0, 1));
        }

        if ($foundBanners->exists(fn($key, $element) => $element != null)) {
            NetworkImpression::register(
                Utils::hexUuidFromBase64UrlWithChecksum($impressionId),
                Utils::hexUuidFromBase64UrlWithChecksum($tid),
                $impressionContext,
                $userContext,
                $foundBanners,
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
        $impressionId = $request->query->get('iid');
        $networkImpression = NetworkImpression::fetchByImpressionId(
            Utils::hexUuidFromBase64UrlWithChecksum($impressionId)
        );
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
                urlencode(self::$adserverId),
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
            'supplyName' => config('app.adserver_name'),
            'supplyTermsUrl' => route('terms-url'),
            'supplyPrivacyUrl' => route('privacy-url'),
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
        if (null === ($networkHost = NetworkHost::fetchByAddress(config('app.adshares_address')))) {
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

    private function getPublisherOrFail(string $publisher): ?User
    {
        if (Utils::isUuidValid($publisher)) {
            $user = User::fetchByUuid($publisher);
        } else {
            try {
                $payoutAddress = WalletAddress::fromString($publisher);
            } catch (InvalidArgumentException) {
                throw new UnprocessableEntityHttpException('Field `page.publisher` must be an ID or account address');
            }
            $user = User::fetchByWalletAddress($payoutAddress);
            if (null === $user) {
                if (config('app.auto_registration_enabled')) {
                    if (!in_array(UserRole::PUBLISHER, config('app.default_user_roles'))) {
                        throw new HttpException(Response::HTTP_FORBIDDEN, 'Cannot register publisher');
                    }
                    $user = User::registerWithWallet($payoutAddress, true);
                }
            }
        }

        if (null === $user) {
            throw new UnprocessableEntityHttpException(
                sprintf('User not found for %s', $publisher)
            );
        }

        if (!$user->isPublisher()) {
            throw new HttpException(Response::HTTP_FORBIDDEN, 'Forbidden');
        }

        return $user;
    }

    private function mapFoundBannerToResult(): Closure
    {
        return function ($item) {
            $mapped = [
                'placementId' => $item['id'],
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
                'rpm' => $item['rpm'],
            ];
            if (isset($item['request_id'])) {
                $mapped['id'] = $item['request_id'];
            }
            return $mapped;
        };
    }
}
