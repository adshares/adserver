<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
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
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Http\Controllers;

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\GzippedStreamedResponse;
use Adshares\Adserver\Http\Response\PaymentDetailsResponse;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\ConversionGroup;
use Adshares\Adserver\Models\EventConversionLog;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\Payment;
use Adshares\Adserver\Models\ServeDomain;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Services\ConversionValidator;
use Adshares\Adserver\Services\EventCaseFinder;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Adserver\Utilities\DomainReader;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Adshares\Demand\Application\Service\PaymentDetailsVerify;
use DateTime;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use function base64_decode;
use function bin2hex;
use function inet_pton;
use function json_decode;
use function sprintf;

class DemandController extends Controller
{
    private const CONTENT_TYPE = 'Content-Type';

    private const PAYMENT_DETAILS_LIMIT_DEFAULT = 1000;

    private const PAYMENT_DETAILS_LIMIT_MAX = 10000;

    private const ONE_PIXEL_GIF_DATA = 'R0lGODlhAQABAIABAP///wAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';

    /** @var PaymentDetailsVerify */
    private $paymentDetailsVerify;

    /** @var CampaignRepository */
    private $campaignRepository;

    /** @var ConversionValidator */
    private $conversionValidator;

    /** @var EventCaseFinder */
    private $eventCaseFinder;

    /** @var LicenseReader */
    private $licenseReader;

    public function __construct(
        PaymentDetailsVerify $paymentDetailsVerify,
        CampaignRepository $campaignRepository,
        ConversionValidator $conversionValidator,
        EventCaseFinder $eventCaseFinder,
        LicenseReader $licenseReader
    ) {
        $this->paymentDetailsVerify = $paymentDetailsVerify;
        $this->campaignRepository = $campaignRepository;
        $this->conversionValidator = $conversionValidator;
        $this->eventCaseFinder = $eventCaseFinder;
        $this->licenseReader = $licenseReader;
    }

    public function serve(Request $request, $id): Response
    {
        $banner = $this->getBanner($id);

        if ('OPTIONS' === $request->getRealMethod()) {
            $response = new Response('', Response::HTTP_NO_CONTENT);
        } else {
            $response = new GzippedStreamedResponse();
        }

        $response->headers->set('Access-Control-Allow-Origin', '*');

        if ('OPTIONS' === $request->getRealMethod()) {
            $response->headers->set('Access-Control-Max-Age', 1728000);

            return $response;
        }

        $isIECompat = $request->query->has('xdr');

        if ('html' === $banner->creative_type) {
            $mime = 'text/html';
        } else {
            $mime = 'image/png';
        }

        $response->setCallback(
            function () use ($response, $banner, $isIECompat) {
                if (!$isIECompat) {
                    echo $banner->creative_contents;

                    return;
                }

                $headers = [];
                foreach ($response->headers->allPreserveCase() as $name => $value) {
                    if (0 === strpos($name, 'X-')) {
                        $headers[] = "$name:".implode(',', $value);
                    }
                }
                echo implode("\n", $headers)."\n\n";
                echo base64_encode($banner->creative_contents);
            }
        );

        $response->setCache(
            [
                'last_modified' => $banner->updated_at,
                'max_age' => 3600 * 24 * 30,
                's_maxage' => 3600 * 24 * 30,
                'private' => false,
                'public' => true,
            ]
        );
        $response->headers->addCacheControlDirective('no-transform');

        $response->headers->set(self::CONTENT_TYPE, ($isIECompat ? 'text/base64,' : '').$mime);

        return $response;
    }

    private function getBanner(string $id): Banner
    {
        $banner = Banner::fetchBanner($id);

        if ($banner === null) {
            abort(404);
        }

        return $banner;
    }

    public function viewScript(Request $request): StreamedResponse
    {
        $params = [json_encode($request->getSchemeAndHttpHost())];

        $jsPath = public_path('-/view.js');

        $response = new StreamedResponse();
        $response->setCallback(
            function () use ($jsPath, $params) {
                echo str_replace(
                    [
                        "'{{ ORIGIN }}'",
                    ],
                    $params,
                    file_get_contents($jsPath)
                );
            }
        );

        $response->headers->set(self::CONTENT_TYPE, 'text/javascript');

        $response->setCache(
            [
                'etag' => md5(md5_file($jsPath).implode(':', $params)),
                'last_modified' => new DateTime('@'.filemtime($jsPath)),
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

    public function click(Request $request, string $bannerId): RedirectResponse
    {
        $banner = $this->getBanner($bannerId);

        $campaign = $banner->campaign;
        $user = $campaign->user;

        $url = $campaign->landing_url;
        $requestHeaders = $request->headers->all();

        $caseId = $request->query->get('cid');

        $url = Utils::addUrlParameter($url, 'cid', $caseId);
        $response = new RedirectResponse($url);
        $impressionId = $request->query->get('iid');

        if ($impressionId) {
            $tid = Utils::attachOrProlongTrackingCookie(
                $request,
                $response,
                '',
                new DateTime(),
                $impressionId
            );
        } else {
            $tid = $request->cookies->get('tid');
        }

        $trackingId = $tid
            ? Utils::hexUuidFromBase64UrlWithChecksum($tid)
            : $caseId;
        $payTo = $request->query->get('pto');
        $publisherId = $request->query->get('pid');

        $context = Utils::decodeZones($request->query->get('ctx'));
        $keywords = $context['page']['keywords'];

        $response->send();

        $ip = bin2hex(inet_pton($request->getClientIp()));

        $hasCampaignClickConversion = $campaign->hasClickConversion();
        $eventType = $hasCampaignClickConversion ? EventLog::TYPE_SHADOW_CLICK : EventLog::TYPE_CLICK;
        $eventId = Utils::createCaseIdContainingEventType($caseId, $eventType);

        if ($hasCampaignClickConversion) {
            EventConversionLog::create(
                $caseId,
                $eventId,
                $bannerId,
                $context['page']['zone'] ?? null,
                $trackingId,
                $publisherId,
                $campaign->uuid,
                $user->uuid,
                $payTo,
                $ip,
                $requestHeaders,
                Utils::getImpressionContextArray($request),
                $keywords,
                $eventType
            );
        } else {
            if (EventLog::eventClicked($caseId) > 0) {
                EventLog::create(
                    $caseId,
                    $eventId,
                    $bannerId,
                    $context['page']['zone'] ?? null,
                    $trackingId,
                    $publisherId,
                    $campaign->uuid,
                    $user->uuid,
                    $payTo,
                    $ip,
                    $requestHeaders,
                    Utils::getImpressionContextArray($request),
                    $keywords,
                    $eventType
                );
            }
        }

        return $response;
    }

    public function view(Request $request, string $bannerId): Response
    {
        $this->validateEventRequest($request);
        $requestHeaders = $request->headers->all();

        $caseId = $request->query->get('cid');
        $eventId = Utils::createCaseIdContainingEventType($caseId, EventLog::TYPE_VIEW);

        $response = new Response();
        $impressionId = $request->query->get('iid');

        if ($impressionId) {
            $tid = Utils::attachOrProlongTrackingCookie(
                $request,
                $response,
                '',
                new DateTime(),
                $impressionId
            );
        } else {
            $tid = $request->cookies->get('tid');
        }

        $trackingId = $tid
            ? Utils::hexUuidFromBase64UrlWithChecksum($tid)
            : $caseId;

        $payTo = $request->query->get('pto');
        $publisherId = $request->query->get('pid');

        $context = Utils::decodeZones($request->query->get('ctx'));
        $keywords = $context['page']['keywords'] ?? '';

        $adUserEndpoint = config('app.aduser_base_url');

        if ($adUserEndpoint) {
            $adUserUrl = sprintf(
                '%s/register/%s/%s/%s.htm',
                $adUserEndpoint,
                urlencode(config('app.adserver_id')),
                $tid ?: Utils::base64UrlEncodeWithChecksumFromBinUuidString(hex2bin($caseId)),
                $impressionId ?? Utils::urlSafeBase64Encode(random_bytes(8))
            );
        } else {
            $adUserUrl = null;
        }

        $response->setContent(view(
            'demand/view-event',
            [
                'log_url' => (new SecureUrl(route('banner-context', ['id' => $eventId])))->toString(),
                'view_script_url' => (new SecureUrl(url('-/view.js')))->toString(),
                'aduser_url' => $adUserUrl,
            ]
        ));

        $response->send();

        $banner = $this->getBanner($bannerId);
        $campaign = $banner->campaign;
        $user = $campaign->user;

        $ip = bin2hex(inet_pton($request->getClientIp()));
        EventLog::create(
            $caseId,
            $eventId,
            $bannerId,
            $context['page']['zone'] ?? null,
            $trackingId,
            $publisherId,
            $campaign->uuid,
            $user->uuid,
            $payTo,
            $ip,
            $requestHeaders,
            Utils::getImpressionContextArray($request),
            $keywords,
            EventLog::TYPE_VIEW
        );

        return $response;
    }

    public function conversion(string $uuid, Request $request): JsonResponse
    {
        if (null === $request->input('cid') && null === $request->cookies->get('tid')) {
            $baseUrl = (new SecureUrl($request->getSchemeAndHttpHost()))->toString();
            $baseUrlNext = $this->selectNextBaseUrl($baseUrl);

            if (null === $baseUrlNext) {
                throw new BadRequestHttpException('Missing case id');
            }

            return redirect($baseUrlNext.route(Route::currentRouteName(), ['uuid' => $uuid], false));
        }

        $response = self::json(['status' => 'OK'], Response::HTTP_OK);

        $this->processConversion($uuid, $request, $response);

        return $response;
    }

    public function conversionGif(string $uuid, Request $request): Response
    {
        if (null === $request->input('cid') && null === $request->cookies->get('tid')) {
            $baseUrl = (new SecureUrl($request->getSchemeAndHttpHost()))->toString();
            $baseUrlNext = $this->selectNextBaseUrl($baseUrl);

            if (null === $baseUrlNext) {
                throw new BadRequestHttpException('Missing case id');
            }

            return redirect($baseUrlNext.route(Route::currentRouteName(), ['uuid' => $uuid], false));
        }

        $response = new Response(base64_decode(self::ONE_PIXEL_GIF_DATA));
        $response->headers->set('Content-Type', 'image/gif');
        $response->send();

        try {
            $this->processConversion($uuid, $request);
        } catch (BadRequestHttpException|NotFoundHttpException $exception) {
            Log::error(
                sprintf(
                    '[DemandController] conversion error %d (%s)',
                    $exception->getStatusCode(),
                    $exception->getMessage()
                )
            );
        }

        return $response;
    }

    public function conversionClick(string $campaignUuid, Request $request): JsonResponse
    {
        if (null === $request->input('cid') && null === $request->cookies->get('tid')) {
            $baseUrl = (new SecureUrl($request->getSchemeAndHttpHost()))->toString();
            $baseUrlNext = $this->selectNextBaseUrl($baseUrl);

            if (null === $baseUrlNext) {
                throw new BadRequestHttpException('Missing case id');
            }

            return redirect($baseUrlNext.route(Route::currentRouteName(), ['campaign_uuid' => $campaignUuid], false));
        }

        $response = self::json(['status' => 'OK'], Response::HTTP_OK);

        $this->processConversionClick($campaignUuid, $request, $response);

        return $response;
    }

    public function conversionClickGif(string $campaignUuid, Request $request): Response
    {
        if (null === $request->input('cid') && null === $request->cookies->get('tid')) {
            $baseUrl = (new SecureUrl($request->getSchemeAndHttpHost()))->toString();
            $baseUrlNext = $this->selectNextBaseUrl($baseUrl);

            if (null === $baseUrlNext) {
                throw new BadRequestHttpException('Missing case id');
            }

            return redirect($baseUrlNext.route(Route::currentRouteName(), ['campaign_uuid' => $campaignUuid], false));
        }

        $response = new Response(base64_decode(self::ONE_PIXEL_GIF_DATA));
        $response->headers->set('Content-Type', 'image/gif');
        $response->send();

        try {
            $this->processConversionClick($campaignUuid, $request);
        } catch (BadRequestHttpException|NotFoundHttpException $exception) {
            Log::error(
                sprintf(
                    '[DemandController] click conversion error %d (%s)',
                    $exception->getStatusCode(),
                    $exception->getMessage()
                )
            );
        }

        return $response;
    }

    private function validateEventRequest(Request $request): void
    {
        if (!$request->query->has('ctx')
            || !$request->query->has('cid')
            || !$request->query->has('pto')
            || !$request->query->has('pid')
        ) {
            throw new BadRequestHttpException('Invalid parameters.');
        }
    }

    public function context(Request $request, string $eventId): Response
    {
        $response = new Response();

        $response->setContent(base64_decode(self::ONE_PIXEL_GIF_DATA));
        $response->headers->set(self::CONTENT_TYPE, 'image/gif');
        $response->send();

        $context = Utils::urlSafeBase64Decode($request->query->get('k') ?? '');
        $decodedContext = json_decode($context);

        try {
            $event = EventLog::fetchOneByEventId($eventId);
            $event->our_context = $decodedContext;
            $event->domain = isset($decodedContext->url) ? DomainReader::domain($decodedContext->url) : null;
            $event->save();
        } catch (ModelNotFoundException $e) {
            Log::warning($e->getMessage());
        }

        return $response;
    }

    public function paymentDetails(
        string $transactionId,
        string $accountAddress,
        string $date,
        string $signature,
        Request $request
    ): PaymentDetailsResponse {
        $transactionIdDecoded = AdsUtils::decodeTxId($transactionId);
        $accountAddressDecoded = AdsUtils::decodeAddress($accountAddress);
        $datetime = DateTime::createFromFormat(DateTime::ATOM, $date);

        if ($transactionIdDecoded === null || $accountAddressDecoded === null) {
            throw new BadRequestHttpException('Input data are invalid.');
        }

        if (!$this->paymentDetailsVerify->verify($signature, $transactionId, $accountAddress, $datetime)) {
            throw new BadRequestHttpException(sprintf('Signature %s is invalid.', $signature));
        }

        $payments = Payment::fetchPayments($transactionIdDecoded, $accountAddressDecoded);

        if (!$payments) {
            throw new NotFoundHttpException(
                sprintf(
                    'Payment for given transaction %s is not found.',
                    $transactionId
                )
            );
        }

        $limit = (int)$request->get('limit', self::PAYMENT_DETAILS_LIMIT_DEFAULT);
        if ($limit > self::PAYMENT_DETAILS_LIMIT_MAX) {
            throw new BadRequestHttpException(sprintf('Maximum limit of %d exceeded', self::PAYMENT_DETAILS_LIMIT_MAX));
        }

        return new PaymentDetailsResponse(EventLog::fetchEvents(
            $payments->pluck('id'),
            $limit,
            (int)$request->get('offset', 0)
        ));
    }

    public function inventoryList(Request $request): JsonResponse
    {
        $licenceTxFee = $this->licenseReader->getFee(Config::LICENCE_TX_FEE);
        $operatorTxFee = Config::fetchFloatOrFail(Config::OPERATOR_TX_FEE);

        $campaigns = [];
        foreach ($this->campaignRepository->fetchActiveCampaigns() as $i => $campaign) {
            $banners = [];

            foreach ($campaign->ads as $banner) {
                $bannerArray = $banner->toArray();

                if (Banner::STATUS_ACTIVE !== (int)$bannerArray['status']) {
                    continue;
                }

                $bannerPublicId = $bannerArray['uuid'];
                $banners[] = [
                    'id' => $bannerArray['uuid'],
                    'width' => $bannerArray['creative_width'],
                    'height' => $bannerArray['creative_height'],
                    'type' => $bannerArray['creative_type'],
                    'checksum' => $bannerArray['creative_sha1'],
                    'serve_url' => $this->changeHost(route('banner-serve', ['id' => $bannerPublicId]), $request),
                    'click_url' => $this->changeHost(route('banner-click', ['id' => $bannerPublicId]), $request),
                    'view_url' => $this->changeHost(route('banner-view', ['id' => $bannerPublicId]), $request),
                ];
            }

            $campaigns[] = [
                'id' => $campaign->uuid,
                'landing_url' => $campaign->landing_url,
                'date_start' => $campaign->time_start,
                'date_end' => $campaign->time_end,
                'created_at' => $campaign->created_at->format(DateTime::ATOM),
                'updated_at' => $campaign->updated_at->format(DateTime::ATOM),
                'max_cpc' => $campaign->max_cpc,
                'max_cpm' => $campaign->max_cpm,
                'budget' => $this->calculateBudgetAfterFees($campaign->budget, $licenceTxFee, $operatorTxFee),
                'banners' => $banners,
                'targeting_requires' => (array)$campaign->targeting_requires,
                'targeting_excludes' => (array)$campaign->targeting_excludes,
                'address' => AdsUtils::normalizeAddress(config('app.adshares_address')),
            ];
        }

        return self::json($campaigns, Response::HTTP_OK);
    }

    private function calculateBudgetAfterFees(int $budget, float $licenceTxFee, float $operatorTxFee): int
    {
        $licenceFee = (int)floor($budget * $licenceTxFee);
        $budgetAfterFee = $budget - $licenceFee;
        $operatorFee = (int)floor($budgetAfterFee * $operatorTxFee);

        return $budgetAfterFee - $operatorFee;
    }

    private function changeHost(string $url, Request $request): string
    {
        $currentHost = $request->getSchemeAndHttpHost();
        $bannerHost = config('app.adserver_banner_host');

        return str_replace($currentHost, $bannerHost, $url);
    }

    private function validateConversionAdvanced(Request $request, string $secret, string $conversionUuid): void
    {
        $signature = $request->input('sig');
        if (null === $signature) {
            throw new BadRequestHttpException('No signature provided');
        }

        $nonce = $request->input('nonce');
        if (null === $nonce) {
            throw new BadRequestHttpException('No nonce provided');
        }

        $timestampCreated = $request->input('ts');
        if (null === $timestampCreated) {
            throw new BadRequestHttpException('No timestamp provided');
        }

        $timestampCreated = (int)$timestampCreated;
        if ($timestampCreated <= 0) {
            throw new BadRequestHttpException('Invalid timestamp');
        }

        $value = $request->input('value', '');

        try {
            $isSignatureValid = $this->conversionValidator->validateSignature(
                $signature,
                $conversionUuid,
                $nonce,
                $timestampCreated,
                $value,
                $secret
            );
        } catch (RuntimeException $exception) {
            Log::warning(sprintf('[DemandController] Conversion error: %s', $exception->getMessage()));

            $isSignatureValid = false;
        }

        if (!$isSignatureValid) {
            throw new BadRequestHttpException('Invalid signature');
        }
    }

    private function processConversion(string $uuid, Request $request, Response $response = null): void
    {
        $conversionDefinition = $this->fetchConversionDefinitionOrFail($uuid);

        $isAdvanced = $conversionDefinition->isAdvanced();

        $value = $this->getConversionValue($request, $conversionDefinition);

        $campaign = Campaign::find($conversionDefinition->campaign_id);

        if ($isAdvanced) {
            $secret = $campaign->secret;

            $this->validateConversionAdvanced($request, $secret, $uuid);
        }

        $conversionDefinitionId = $conversionDefinition->id;
        $campaignPublicId = $campaign->uuid;

        $cases = $this->findCasesConnectedWithConversion($request, $campaignPublicId);

        if (!$conversionDefinition->isRepeatable()) {
            $caseIds = array_keys($cases);

            if (ConversionGroup::containsConversionMatchingCaseIds($conversionDefinitionId, $caseIds)) {
                throw new BadRequestHttpException('Repeated conversion');
            }
        }

        if (null !== $response) {
            $response->send();
        }

        $advertiserId = $campaign->user->uuid;
        $groupId = Uuid::v4()->toString();
        $headers = $request->headers->all();
        $ip = bin2hex(inet_pton($request->getClientIp()));
        $impressionContext = Utils::getImpressionContextArray($request);

        $viewEventsData = $this->getViewEventsData($cases);

        DB::beginTransaction();

        foreach ($cases as $caseId => $weight) {
            $viewEventData = $viewEventsData[$caseId];

            $event = EventConversionLog::createWithUserData(
                $caseId,
                Uuid::v4()->toString(),
                $viewEventData['bannerId'],
                $viewEventData['zoneId'],
                $viewEventData['trackingId'],
                $viewEventData['publisherId'],
                $campaignPublicId,
                $advertiserId,
                $viewEventData['payTo'],
                $ip,
                $headers,
                $impressionContext,
                '',
                EventLog::TYPE_CONVERSION,
                $viewEventData['humanScore'],
                $viewEventData['ourUserdata']
            );

            $eventId = $event->id;
            $partialValue = (int)floor($value * $weight);
            ConversionGroup::register($caseId, $groupId, $eventId, $conversionDefinitionId, $partialValue, $weight);
        }

        DB::commit();
    }

    private function processConversionClick(string $campaignUuid, Request $request, Response $response = null): void
    {
        $campaign = Campaign::fetchByUuid($campaignUuid);

        if (null === $campaign) {
            throw new NotFoundHttpException('No matching campaign found');
        }

        if (!$campaign->hasClickConversion()) {
            throw new BadRequestHttpException('Click conversion not supported');
        }

        if ($campaign->hasClickConversionAdvanced()) {
            $secret = $campaign->secret;

            $this->validateConversionAdvanced($request, $secret, $campaignUuid);
        }

        $campaignPublicId = $campaign->uuid;

        $cases = $this->findCasesConnectedWithConversion($request, $campaignPublicId);

        if (null !== $response) {
            $response->send();
        }

        $advertiserId = $campaign->user->uuid;
        $headers = $request->headers->all();
        $ip = bin2hex(inet_pton($request->getClientIp()));
        $impressionContext = Utils::getImpressionContextArray($request);

        $viewEventsData = $this->getViewEventsData($cases);

        DB::beginTransaction();

        foreach ($cases as $caseId => $weight) {
            $viewEventData = $viewEventsData[$caseId];

            if (EventLog::eventClicked($caseId) > 0) {
                EventLog::createWithUserData(
                    $caseId,
                    Utils::createCaseIdContainingEventType($caseId, EventLog::TYPE_CLICK),
                    $viewEventData['bannerId'],
                    $viewEventData['zoneId'],
                    $viewEventData['trackingId'],
                    $viewEventData['publisherId'],
                    $campaignPublicId,
                    $advertiserId,
                    $viewEventData['payTo'],
                    $ip,
                    $headers,
                    $impressionContext,
                    '',
                    EventLog::TYPE_CLICK,
                    $viewEventData['humanScore'],
                    $viewEventData['ourUserdata']
                );
            }
        }

        DB::commit();
    }

    private function fetchConversionDefinitionOrFail(string $uuid): ConversionDefinition
    {
        if (32 !== strlen($uuid)) {
            throw new BadRequestHttpException('Invalid conversion id');
        }

        $conversionDefinition = ConversionDefinition::fetchByUuid($uuid);
        if (!$conversionDefinition) {
            throw new NotFoundHttpException('No conversion found');
        }

        return $conversionDefinition;
    }

    private function getConversionValue(Request $request, ConversionDefinition $conversionDefinition): int
    {
        if (!$conversionDefinition->isAdvanced()) {
            return $conversionDefinition->value;
        }

        if ($request->has('value')) {
            $value = is_numeric($request->input('value')) ? $request->input('value') * 10 ** 11 : null;
        } else {
            $value = $conversionDefinition->value;
        }
        if (null === $value) {
            throw new BadRequestHttpException('No value provided');
        }

        $value = (int)$value;
        if ($value <= 0) {
            throw new BadRequestHttpException('Invalid value');
        }

        return $value;
    }

    private function findCasesConnectedWithConversion(Request $request, string $campaignPublicId): array
    {
        $cid = $request->input('cid');
        if (null !== $cid) {
            $results = $this->eventCaseFinder->findByCaseId($campaignPublicId, $cid);
        } else {
            $tid = $request->cookies->get('tid') ? Utils::hexUuidFromBase64UrlWithChecksum(
                $request->cookies->get('tid')
            ) : null;

            if (null === $tid) {
                throw new BadRequestHttpException('Missing case id');
            }

            $results = $this->eventCaseFinder->findByTrackingId($campaignPublicId, $tid);
        }

        if (0 === count($results)) {
            throw new NotFoundHttpException('No matching case found');
        }

        return $results;
    }

    private function getViewEventsData(array $cases): array
    {
        $viewEventsData = [];
        $eventPublicIds = [];
        foreach ($cases as $caseId => $weight) {
            $eventPublicIds[] = Utils::createCaseIdContainingEventType($caseId, EventLog::TYPE_VIEW);
        }
        $viewEvents = EventLog::fetchByEventIds($eventPublicIds);

        foreach ($viewEvents as $viewEvent) {
            /** @var $viewEvent EventLog */
            $viewEventsData[$viewEvent->case_id] = [
                'bannerId' => $viewEvent->banner_id,
                'zoneId' => $viewEvent->zone_id,
                'trackingId' => $viewEvent->tracking_id,
                'publisherId' => $viewEvent->publisher_id,
                'payTo' => $viewEvent->pay_to,
                'humanScore' => null !== $viewEvent->human_score ? (float)$viewEvent->human_score : null,
                'ourUserdata' => $viewEvent->our_userdata,
            ];
        }

        return $viewEventsData;
    }

    private function selectNextBaseUrl(string $baseUrl): ?string
    {
        $urls = ServeDomain::fetch();

        if (empty($urls)) {
            return null;
        }

        $key = array_search($baseUrl, $urls, true);

        if (false === $key) {
            return $urls[0];
        }

        $nextKey = $key + 1;
        if ($nextKey >= count($urls)) {
            return null;
        }

        return $urls[$nextKey];
    }
}
