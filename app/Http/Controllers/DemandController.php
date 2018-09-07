<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer.  If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Http\Controllers;

use Adshares\Adserver\Http\GzippedStreamedResponse;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\EventLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * API commands used to serve banners and log relevant events.
 */
class DemandController extends Controller
{
    public function serve(Request $request, $id)
    {
        $banner = Banner::find($id);
        if (empty($banner)) {
            abort(404);
        }

        // TODO:  ID / UUID here =>
        // TODO: no need for obfuscation
        // TODO: Yoda smell stuff here // this should be cleaned up

        if ('OPTIONS' == $request->getRealMethod()) {
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

        if ('OPTIONS' == $request->getRealMethod()) {
            $response->headers->set('Access-Control-Max-Age', 1728000);

            return $response;
        }

        $isIECompat = $request->query->has('xdr');

        if ('html' == $banner->creative_type) {
            $mime = 'text/html';
        } else {
            $mime = 'image/png';
        }

        $tid = Utils::attachTrackingCookie(
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
                        $headers[] = "$name:" . implode(',', $value);
                    }
                }
                echo implode("\n", $headers) . "\n\n";
                echo base64_encode($banner->creative_contents);
            }
        );

        $cid = Utils::createTrackingId(config('app.adserver_secret'));

        $log = new EventLog();
        $log->banner_id = $banner->id;
        $log->cid = Utils::getRawTrackingId($cid);
        $log->tid = Utils::getRawTrackingId($tid);
        $log->ip = bin2hex(inet_pton($request->getClientIp()));
        $log->event_type = 'request';
        $log->save();

        $response->headers->set('X-Adshares-Cid', $cid);
        $response->headers->set('X-Adshares-Lid', $log->id);

        if (!$response->isNotModified($request)) {
            $response->headers->set('Content-Type', ($isIECompat ? 'text/base64,' : '') . $mime);
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
                'etag' => md5(md5_file($jsPath) . implode(':', $params)),
                'last_modified' => new \DateTime('@' . filemtime($jsPath)),
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

    public function click(Request $request, $id)
    {
        $banner = Banner::with('Campaign')->find($id);
        if (!$banner) {
            throw new NotFoundHttpException();
        }

        $campaign = $banner->campaign;

        $url = $campaign->landing_url;
        $logIp = bin2hex(inet_pton($request->getClientIp()));

        $cid = Utils::getRawTrackingId($request->query->get('cid'));
        $pid = $request->query->get('pid');
        $tid = Utils::getRawTrackingId($request->cookies->get('tid')) ?: $logIp;
        $payTo = $request->query->get('pto');

        $log = new EventLog();
        $log->cid = $cid;
        $log->publisher_event_id = $pid;
        $log->banner_id = $id;
        $log->tid = $tid;
        $log->ip = $logIp;
        $log->pay_to = $payTo;
        $log->event_type = 'click';
        $log->their_context = Utils::getImpressionContext($request);
        $log->save();

        // TODO: fix adpay
        // $adpayService = $this->container->has('adpay') ? $this->container->get('adpay') : null;
        // $adpayService instanceof Adpay;
        //
        // if ($adpayService) {
        //     $adpayService->addEvents([
        //         $log->getAdpayJson(),
        //     ]);
        // }

        $response = new \Symfony\Component\HttpFoundation\Response($url);

        // last click id will be used to track conversions
        $response->headers->setCookie(
            new \Symfony\Component\HttpFoundation\Cookie(
                'cid',
                $request->query->get('cid'),
                new \DateTime('+ 1 month')
            )
        );

        $response->setContent(
            sprintf(
                '<!DOCTYPE html>
<html>
  <head>
      <meta charset="UTF-8" />
      <meta http-equiv="refresh" content="5;url=%1$s" />

      <title>Redirecting to %1$s</title>
  </head>
  <body>
      Redirecting to <a href="%1$s">%1$s</a>.
  </body>
</html>',
                htmlspecialchars($url, ENT_QUOTES, 'UTF-8')
            )
        );

        return $response;
    }

    public function view(Request $request, $id)
    {
        if ($request->query->get('r')) {
            $url = $request->query->get('r');
            $request->query->remove('r');
        }

        $logIp = bin2hex(inet_pton($request->getClientIp()));

        $cid = Utils::getRawTrackingId($request->query->get('cid'));
        $pid = $request->query->get('pid');
        $tid = Utils::getRawTrackingId($request->cookies->get('tid')) ?: $logIp;
        $payTo = $request->query->get('pto');

        $keywords = json_decode(Utils::urlSafeBase64Decode($request->query->get('k')), true);

        $lid = $request->query->get('lid');
        if ($lid) {
            $log = EventLog::find($lid);
        }
        if (empty($log)) {
            $log = new EventLog();
        }
        $log->publisher_event_id = $pid;

        $log->cid = $cid;
        $log->banner_id = $id;
        $log->pay_to = $payTo;
        $log->tid = $tid;
        $log->ip = $logIp;
        $log->their_context = Utils::getImpressionContext($request);
        $log->event_type = 'view';
        $log->their_userdata = $keywords;

        $log->save();

        $aduser_endpoint = config('app.aduser_endpoint');

        if ($aduser_endpoint) {
            $iid = $request->query->get('iid') ?: Utils::createTrackingId($this->getParameter('secret'));
            $backUrl = route(
                'log-keywords',
                [
                    'iid' => $iid,
                    'log_id' => $log->id,
                    'r' => $url,
                ]
            );

            $response = new RedirectResponse(
                $aduser_endpoint . '/pixel/' . $iid . '?r=' . Utils::urlSafeBase64Encode($backUrl)
            );
        } else {
            throw new Exception('ADAPY');
            $response = new Response();

            //transparent 1px gif
            $response->setContent(base64_decode('R0lGODlhAQABAIABAP///wAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=='));
            $response->headers->set('Content-Type', 'image/gif');

            $adpayService = $this->container->has('adpay') ? $this->container->get('adpay') : null;
            $adpayService instanceof Adpay;

            if ($adpayService) {
                $adpayService->addEvents(
                    [
                        $log->getAdpayJson(),
                    ]
                );
            }
        }

        return $response;
    }

    public function logKeywords(Request $request, $log_id)
    {
        $url = Utils::urlSafeBase64Decode($request->query->get('r'));

        // GET kewords from aduser
        $impressionId = $request->query->get('iid');
        $aduser_endpoint = config('app.aduser_endpoint');
        $userdata = ($aduser_endpoint && $impressionId) ?
            json_decode(file_get_contents("{$aduser_endpoint}/getData/{$impressionId}"), true) : [];

        $log = EventLog::find($log_id);
        if (!empty($log)) {
            $log->our_userdata = $userdata['keywords'];
            $log->human_score = $userdata['human_score'];
            $log->user_id = $userdata['user_id'];
            $log->save();
        }

        $url = Utils::addUrlParameter($url, 's', parse_url($aduser_endpoint, PHP_URL_HOST));
        $url = Utils::addUrlParameter($url, 'k', Utils::urlSafeBase64Encode(json_encode($userdata['keywords'])));

        $response = new RedirectResponse($url);

        // $adpayService = $this->container->has('adpay') ? $this->container->get('adpay') : null;
        // $adpayService instanceof Adpay;
        //
        // if ($adpayService && $log->getOurContext() && $log->getUserId()) {
        //     $adpayService->addEvents([
        //       $log->getAdpayJson(),
        //   ]);
        // }

        return $response;
    }

    public function logContext(Request $request, $log_id)
    {
        $keywords = json_decode(Utils::urlSafeBase64Decode($request->query->get('k')), true);

        $context = Utils::getImpressionContext($request, $keywords);

        $log = EventLog::find($log_id);

        if (!empty($log)) {
            $log->our_context = $context;
            $log->save();
        }

        $response = Response::make(base64_decode('R0lGODlhAQABAIABAP///wAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=='));
        $response->header('Content-Type', 'image/gif');

        // $adpayService = $this->container->has('adpay') ? $this->container->get('adpay') : null;
        // $adpayService instanceof Adpay;
        //
        // if ($adpayService && $log->getOurContext() && $log->getUserId()) {
        //     $adpayService->addEvents([
        //       $log->getAdpayJson(),
        //     ]);
        // }

        return $response;
    }
}
