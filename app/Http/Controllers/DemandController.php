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
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Models\Payment;
use Adshares\Adserver\Utilities\AdsUtils;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use function hex2bin;

/**
 * API commands used to serve banners and log relevant events.
 */
class DemandController extends Controller
{
    public function serve(Request $request, $id)
    {
        $banner = Banner::where('uuid', hex2bin($id))->first();

        if (empty($banner)) {
            abort(404);
        }

        if ('OPTIONS' === $request->getRealMethod()) {
            $response = new Response('', 204);
        } else {
            $response = new GzippedStreamedResponse();
        }

        if ($request->headers->has('Origin')) {
            $response->headers->set('Access-Control-Allow-Origin', $request->headers->get('Origin'));
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $response->headers->set('Access-Control-Expose-Headers', 'X-Adshares-Cid, X-Adshares-Lid');
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
            config('app.adserver_secret'),
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

        $eventId = Utils::getRawTrackingId(Utils::createTrackingId(config('app.adserver_secret')));

        $log = new EventLog();
        $log->banner_id = $banner->uuid;
        $log->event_id = $eventId;
        $log->user_id = Utils::getRawTrackingId($tid);
        $log->ip = bin2hex(inet_pton($request->getClientIp()));
        $log->headers = $request->headers->all();
        $log->event_type = EventLog::TYPE_REQUEST;
        $log->save();

        $response->headers->set('X-Adshares-Cid', $eventId);
        $response->headers->set('X-Adshares-Lid', $log->id);

        if (!$response->isNotModified($request)) {
            $response->headers->set('Content-Type', ($isIECompat ? 'text/base64,' : '').$mime);
        }

        return $response;
    }

    public function viewScript(Request $request)
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

    public function click(Request $request, string $bannerId): RedirectResponse
    {
        $banner = Banner::with('Campaign')->where('uuid', hex2bin($bannerId))->first();
        if (!$banner) {
            throw new NotFoundHttpException();
        }

        $campaign = $banner->campaign;

        $url = $campaign->landing_url;
        $logIp = bin2hex(inet_pton($request->getClientIp()));
        $requestHeaders = $request->headers->all();

        $eventId = $request->query->get('cid');
        $trackingId = Utils::getRawTrackingId($request->cookies->get('tid')) ?: $logIp;
        $payTo = $request->query->get('pto');
        $publisherId = $request->query->get('pid');

        $context = Utils::decodeZones($request->query->get('ctx'));
        $keywords = $context['page']['keywords'];

        $response = new RedirectResponse($url);
        $response->send();

        $log = new EventLog();
        $log->event_id = $eventId;
        $log->banner_id = $bannerId;
        $log->user_id = $trackingId;
        $log->zone_id = $context['page']['zone'];
        $log->publisher_id = $publisherId;
        $log->pay_to = $payTo;
        $log->ip = $logIp;
        $log->headers = $requestHeaders;
        $log->their_context = Utils::getImpressionContext($request);
        $log->event_type = EventLog::TYPE_CLICK;
        $log->their_userdata = $keywords;
        $log->save();

        return $response;
    }

    public function view(Request $request, string $bannerId)
    {
        $logIp = bin2hex(inet_pton($request->getClientIp()));
        $requestHeaders = $request->headers->all();

        $eventId = $request->query->get('cid');
        $trackingId = Utils::getRawTrackingId($request->cookies->get('tid')) ?: $logIp;
        $payTo = $request->query->get('pto');
        $publisherId = $request->query->get('pid');

        $context = Utils::decodeZones($request->query->get('ctx'));
        $keywords = $context['page']['keywords'];

        $adUserEndpoint = config('app.aduser_external_location');
        $response = new SymfonyResponse();

        if ($adUserEndpoint) {
            $impressionId = $request->query->get('iid') ?: Utils::createTrackingId($this->getParameter('secret'));

            $demandTrackingId = Utils::attachOrProlongTrackingCookie(
                config('app.adserver_secret'),
                $request,
                $response,
                '',
                new DateTime(),
                $impressionId
            );

            $adUserUrl = sprintf(
                '%s/register/%s/%s/%s.gif',
                $adUserEndpoint,
                urlencode(config('app.adserver_id')),
                $demandTrackingId,
                $impressionId
            );

            $response->headers->set('Location', $adUserUrl);
        }

        $response->send();

        $log = new EventLog();
        $log->event_id = $eventId;
        $log->banner_id = $bannerId;
        $log->user_id = $trackingId;
        $log->zone_id = $context['page']['zone'];
        $log->publisher_id = $publisherId;
        $log->pay_to = $payTo;
        $log->ip = $logIp;
        $log->headers = $requestHeaders;
        $log->their_context = Utils::getImpressionContext($request);
        $log->event_type = EventLog::TYPE_VIEW;
        $log->their_userdata = $keywords;
        $log->save();

        return $response;
    }

    public function paymentDetails(
        string $transactionId,
        string $accountAddress,
        string $date,
        string $signature
    ): JsonResponse {
        $transactionIdDecoded = AdsUtils::decodeTxId($transactionId);
        $accountAddressDecoded = AdsUtils::decodeAddress($accountAddress);

        if ($transactionIdDecoded === null || $accountAddressDecoded === null) {
            throw new BadRequestHttpException('Input data are invalid.');
        }

        $payment = Payment::fetchPayment($transactionIdDecoded, $accountAddressDecoded);

        if (!$payment) {
            throw new NotFoundHttpException(sprintf(
                    'Payment for given transaction %s is not found.',
                    $transactionId
            ));
        }

        $events = EventLog::fetchEvents($payment->id);

        $results = [];

        foreach ($events as $event) {
            $data = $event->toArray();
            $results[] = [
                'event_id' => $data['event_id'],
                'event_type' => $data['event_id'],
                'banner_id' => $data['banner_id'],
                'zone_id' => $data['zone_id'],
                'event_value' => $data['event_value'],
                'paid_amount' => $data['paid_amount'],
            ];
        }

        return new JsonResponse($results);
    }
}
