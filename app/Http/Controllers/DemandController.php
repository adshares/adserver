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
use Adshares\Adserver\Http\GzippedStreamedResponse;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\BannerClassification;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\EventConversionLog;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\Payment;
use Adshares\Adserver\Models\ServeDomain;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Adserver\Utilities\DomainReader;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Adshares\Demand\Application\Service\PaymentDetailsVerify;
use DateTime;
use DateTimeInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

use function base64_decode;
use function json_decode;
use function sprintf;

class DemandController extends Controller
{
    private const CONTENT_TYPE = 'Content-Type';

    private const PAYMENT_DETAILS_LIMIT_DEFAULT = 1000;

    private const PAYMENT_DETAILS_LIMIT_MAX = 10000;

    private const ONE_PIXEL_GIF_DATA = 'R0lGODlhAQABAIABAP///wAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';

    private const SQL_QUERY_SELECT_EVENTS_FOR_PAYMENT_DETAILS_TEMPLATE = <<<SQL
SELECT LOWER(HEX(case_id)) AS case_id, paid_amount AS event_value FROM conversions WHERE payment_id IN (%s)
UNION ALL
SELECT LOWER(HEX(case_id)) AS case_id, paid_amount AS event_value FROM event_logs WHERE payment_id IN (%s)
LIMIT ?
OFFSET ?;
SQL;

    private const PLACEHOLDER_BANNER_ID = '{bid}';

    private const PLACEHOLDER_CASE_ID = '{cid}';

    private const PLACEHOLDER_PUBLISHER_ID = '{pid}';

    private const PLACEHOLDER_SERVER_ID = '{aid}';

    private const PLACEHOLDER_SITE_ID = '{sid}';

    private const PLACEHOLDER_ZONE_ID = '{zid}';

    /** @var PaymentDetailsVerify */
    private $paymentDetailsVerify;

    /** @var CampaignRepository */
    private $campaignRepository;

    /** @var LicenseReader */
    private $licenseReader;

    public function __construct(
        PaymentDetailsVerify $paymentDetailsVerify,
        CampaignRepository $campaignRepository,
        LicenseReader $licenseReader
    ) {
        $this->paymentDetailsVerify = $paymentDetailsVerify;
        $this->campaignRepository = $campaignRepository;
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

        if (Banner::TEXT_TYPE_HTML === $banner->creative_type) {
            $mime = 'text/html';
        } elseif (Banner::TEXT_TYPE_IMAGE === $banner->creative_type) {
            $mime = 'image/png';
        } else {
            $mime = 'text/plain';
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
                        $headers[] = "$name:" . implode(',', $value);
                    }
                }
                echo implode("\n", $headers) . "\n\n";
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

        $response->headers->set(self::CONTENT_TYPE, ($isIECompat ? 'text/base64,' : '') . $mime);

        return $response;
    }

    private function getBanner(string $id): Banner
    {
        $banner = Banner::fetchByPublicId($id);

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
                'etag' => md5(md5_file($jsPath) . implode(':', $params)),
                'last_modified' => new DateTime('@' . filemtime($jsPath)),
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

    public function click(Request $request, string $bannerId)
    {
        $banner = $this->getBanner($bannerId);

        $campaign = $banner->campaign;
        $user = $campaign->user;

        $url = $campaign->landing_url;

        $caseId = $request->query->get('cid');
        $payTo = $request->query->get('pto');
        $publisherId = $request->query->get('pid');
        try {
            $context = Utils::decodeZones($request->query->get('ctx'));
        } catch (RuntimeException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }
        $zoneId = $context['page']['zone'] ?? null;
        $siteId = DomainReader::domain($context['page']['url'] ?? '');

        if ($request->query->get('logonly')) {
            $response = new Response();
        } else {
            $url = $this->replaceLandingUrlPlaceholders(
                $url,
                $caseId,
                $bannerId,
                $publisherId,
                $payTo,
                $siteId,
                $zoneId ?: ''
            );
            $response = new RedirectResponse($url);
        }
        $response->send();

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

        $keywords = $context['page']['keywords'];

        $hasCampaignClickConversion = $campaign->hasClickConversion();
        $eventType = $hasCampaignClickConversion ? EventLog::TYPE_SHADOW_CLICK : EventLog::TYPE_CLICK;
        $eventId = Utils::createCaseIdContainingEventType($caseId, $eventType);

        if ($hasCampaignClickConversion) {
            EventConversionLog::create(
                $caseId,
                $eventId,
                $bannerId,
                $zoneId,
                $trackingId,
                $publisherId,
                $campaign->uuid,
                $user->uuid,
                $payTo,
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
                    $zoneId,
                    $trackingId,
                    $publisherId,
                    $campaign->uuid,
                    $user->uuid,
                    $payTo,
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

        try {
            $context = Utils::decodeZones($request->query->get('ctx'));
        } catch (RuntimeException $exception) {
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }
        $keywords = $context['page']['keywords'] ?? '';

        $adUserEndpoint = config('app.aduser_serve_subdomain') ?
            ServeDomain::current(config('app.aduser_serve_subdomain')) :
            config('app.aduser_base_url');

        if ($adUserEndpoint) {
            $adUserUrl = sprintf(
                '%s/register/%s/%s/%s.html',
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
                'log_url' => ServeDomain::changeUrlHost((new SecureUrl(
                    route('banner-context', ['id' => $eventId])
                ))->toString()),
                'view_script_url' => ServeDomain::changeUrlHost((new SecureUrl(
                    url('-/view.js')
                ))->toString()),
                'aduser_url' => $adUserUrl,
            ]
        ));

        $response->send();

        $banner = $this->getBanner($bannerId);
        $campaign = $banner->campaign;
        $user = $campaign->user;

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
            Utils::getImpressionContextArray($request),
            $keywords,
            EventLog::TYPE_VIEW
        );

        return $response;
    }

    private function validateEventRequest(Request $request): void
    {
        if (
            !$request->query->has('ctx')
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
            if (!$event->domain && isset($event->their_context)) {
                $event->domain = EventLog::getDomainFromContext($event->their_context);
            }
            if (!$event->domain && isset($decodedContext->url)) {
                $event->domain = DomainReader::domain($decodedContext->url);
            }
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
    ): JsonResponse {
        $transactionIdDecoded = AdsUtils::decodeTxId($transactionId);
        $accountAddressDecoded = AdsUtils::decodeAddress($accountAddress);
        $datetime = DateTime::createFromFormat(DateTimeInterface::ATOM, $date);

        if ($transactionIdDecoded === null || $accountAddressDecoded === null) {
            throw new BadRequestHttpException('Input data are invalid.');
        }

        if (!$this->paymentDetailsVerify->verify($signature, $transactionId, $accountAddress, $datetime)) {
            throw new BadRequestHttpException(sprintf('Signature %s is invalid.', $signature));
        }

        $payments = Payment::fetchPayments($transactionIdDecoded, $accountAddressDecoded);

        if ($payments->isEmpty()) {
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

        return self::json($this->fetchPaidConversionsAndEvents(
            $payments->pluck('id')->toArray(),
            $limit,
            (int)$request->get('offset', 0)
        ));
    }

    private static function fetchPaidConversionsAndEvents(array $paymentIds, int $limit, int $offset): array
    {
        if (empty($paymentIds)) {
            return [];
        }

        $whereInPlaceholder = str_repeat('?,', count($paymentIds) - 1) . '?';
        $query = sprintf(
            self::SQL_QUERY_SELECT_EVENTS_FOR_PAYMENT_DETAILS_TEMPLATE,
            $whereInPlaceholder,
            $whereInPlaceholder
        );

        return DB::select($query, array_merge($paymentIds, $paymentIds, [$limit, $offset]));
    }

    public function inventoryList(Request $request): JsonResponse
    {
        $licenceTxFee = $this->licenseReader->getFee(Config::LICENCE_TX_FEE);
        $operatorTxFee = Config::fetchFloatOrFail(Config::OPERATOR_TX_FEE);

        $campaigns = [];

        $activeCampaigns = $this->campaignRepository->fetchActiveCampaigns();

        $bannerIds = [];
        foreach ($activeCampaigns as $campaign) {
            foreach ($campaign->ads as $banner) {
                if (Banner::STATUS_ACTIVE === $banner->status) {
                    $bannerIds[] = $banner->id;
                }
            }
        }
        $bannerClassifications = BannerClassification::fetchClassifiedByBannerIds($bannerIds);
        $cdnEnabled = !empty(config('app.cdn_provider'));

        /** @var Campaign $campaign */
        foreach ($activeCampaigns as $campaign) {
            $banners = [];

            /** @var Banner $banner */
            foreach ($campaign->ads as $banner) {
                $bannerArray = $banner->toArray();

                if (Banner::STATUS_ACTIVE !== (int)$bannerArray['status']) {
                    continue;
                }

                $bannerPublicId = $bannerArray['uuid'];
                $checksum = $bannerArray['creative_sha1'];

                $serveUrl = ($cdnEnabled ? $bannerArray['cdn_url'] : null) ?? ServeDomain::changeUrlHost((new SecureUrl(
                    route('banner-serve', ['id' => $bannerPublicId, 'v' => substr($checksum, 0, 4)])
                ))->toString());
                $clickUrl = ServeDomain::changeUrlHost((new SecureUrl(
                    route('banner-click', ['id' => $bannerPublicId])
                ))->toString());
                $viewUrl = ServeDomain::changeUrlHost((new SecureUrl(
                    route('banner-view', ['id' => $bannerPublicId])
                ))->toString());

                $banners[] = [
                    'id' => $bannerArray['uuid'],
                    'size' => $bannerArray['creative_size'],
                    'type' => $bannerArray['creative_type'],
                    'checksum' => $checksum,
                    'serve_url' => $serveUrl,
                    'click_url' => $clickUrl,
                    'view_url' => $viewUrl,
                    'classification' => $bannerClassifications[$banner->id] ?? new stdClass(),
                ];
            }

            $campaigns[] = [
                'id' => $campaign->uuid,
                'landing_url' => $campaign->landing_url,
                'date_start' => $campaign->time_start,
                'date_end' => $campaign->time_end,
                'created_at' => $campaign->created_at->format(DateTimeInterface::ATOM),
                'updated_at' => $campaign->updated_at->format(DateTimeInterface::ATOM),
                'max_cpc' => $campaign->max_cpc,
                'max_cpm' => $campaign->max_cpm,
                'budget' => $this->calculateBudgetAfterFees($campaign->budget, $licenceTxFee, $operatorTxFee),
                'banners' => $banners,
                'targeting_requires' => (array)$campaign->targeting_requires,
                'targeting_excludes' => (array)$campaign->targeting_excludes,
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

    private function replaceLandingUrlPlaceholders(
        string $landingUrl,
        string $caseId,
        string $bannerId,
        string $publisherId,
        string $serverId,
        string $siteId,
        string $zoneId
    ): string {
        if (false === strpos($landingUrl, self::PLACEHOLDER_CASE_ID)) {
            $landingUrl = Utils::addUrlParameter($landingUrl, 'cid', $caseId);
        } else {
            $landingUrl = str_replace(self::PLACEHOLDER_CASE_ID, $caseId, $landingUrl);
        }

        return str_replace(
            [
                self::PLACEHOLDER_BANNER_ID,
                self::PLACEHOLDER_PUBLISHER_ID,
                self::PLACEHOLDER_SERVER_ID,
                self::PLACEHOLDER_SITE_ID,
                self::PLACEHOLDER_ZONE_ID,
            ],
            [
                $bannerId,
                $publisherId,
                $serverId,
                $siteId,
                $zoneId,
            ],
            $landingUrl
        );
    }
}
