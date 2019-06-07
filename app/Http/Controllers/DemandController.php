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

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\GzippedStreamedResponse;
use Adshares\Adserver\Http\Response\PaymentDetailsResponse;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\Payment;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Utilities\AdsUtils;
use Adshares\Adserver\Utilities\DomainReader;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use Adshares\Demand\Application\Service\PaymentDetailsVerify;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use DateTime;
use function bin2hex;
use function inet_pton;
use function json_decode;
use function sprintf;

class DemandController extends Controller
{
    private const CONTENT_TYPE = 'Content-Type';

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

        if ($request->headers->has('Origin')) {
            $response->headers->set('Access-Control-Allow-Origin', $request->headers->get('Origin'));
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $response->headers->set('Access-Control-Expose-Headers', 'X-Adshares-Cid');
        }

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

        $tid = Utils::attachOrProlongTrackingCookie(
            $request,
            $response,
            $banner->creative_sha1,
            $banner->updated_at
        );

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

        $caseId = (string)Uuid::caseId();
        $eventId = Utils::createCaseIdContainingEventType($caseId, EventLog::TYPE_REQUEST);
        $campaign = $banner->campaign;
        $user = $campaign->user;

        $log = new EventLog();
        $log->banner_id = $banner->uuid;
        $log->case_id = $caseId;
        $log->event_id = $eventId;
        $log->tracking_id = Utils::hexUuidFromBase64UrlWithChecksum($tid);
        $log->advertiser_id = $user->uuid;
        $log->campaign_id = $campaign->uuid;
        $log->ip = bin2hex(inet_pton($request->getClientIp()));
        $log->headers = $request->headers->all();
        $log->event_type = EventLog::TYPE_REQUEST;
        $log->save();

        $response->headers->set('X-Adshares-Cid', $caseId);

        if (!$response->isNotModified($request)) {
            $response->headers->set(self::CONTENT_TYPE, ($isIECompat ? 'text/base64,' : '').$mime);
        }

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
        $eventId = Utils::createCaseIdContainingEventType($caseId, EventLog::TYPE_CLICK);

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
            EventLog::TYPE_CLICK
        );

        EventLog::eventClicked($caseId);

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

    public function conversion(Request $request): Response
    {
        $response = new Response();
        $response->send();
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

        //transparent 1px gif
        $response->setContent(base64_decode('R0lGODlhAQABAIABAP///wAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=='));
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
        string $signature
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

        return new PaymentDetailsResponse(EventLog::fetchEvents($payments->pluck('id')));
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
}
