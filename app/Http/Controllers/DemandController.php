<?php
namespace Adshares\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Adshares\Helper\Utils;
use Adshares\Helper\GzippedStreamedResponse;
use Adshares\Entity\Campaign;
use Adshares\Entity\Banner;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Adshares\Entity\EventLog;

use Symfony\Component\HttpFoundation\Cookie;
use Adshares\Services\Adpay;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * HTTP api that is used by demand adserver to serve banners and log relevant events
 *
 */
class DemandController extends Controller
{

    /**
     * @Route("/serve")
     */
    public function indexAction(Request $request)
    {
    }

    /**
     * @Route("/serve/{id}", name="serve_creative", requirements={"id": "\d+"}, methods={"GET", "OPTIONS"})
     */
    public function serveCreativeAction(Request $request, $id)
    {
        if ($request->getRealMethod() == 'OPTIONS') {
            $response = new Response('', 204);
        } else {
            $response = new GzippedStreamedResponse();
        }
        
        if ($request->headers->has("Origin")) {
            $response->headers->set("Access-Control-Allow-Origin", $request->headers->get("Origin"));
            $response->headers->set("Access-Control-Allow-Credentials", "true");
            $response->headers->set("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
            $response->headers->set("Access-Control-Expose-Headers", "X-Adshares-Cid, X-Adshares-Lid");
        }
        
        if ($request->getRealMethod() == 'OPTIONS') {
            $response->headers->set('Access-Control-Max-Age', 1728000);
            return $response;
        }
        
        $isIECompat = $request->query->has("xdr");
        
        $em = $this->getDoctrine()->getManager();
        
        $banner = Banner::getRepository($em)->find($id);
        if (! $banner) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }
        
        if ($banner->getCreativeType() == 'html') {
            $mime = "text/html";
        } else {
            $mime = "image/png";
        }
        
        $tid = Utils::attachTrackingCookie($this->getParameter('secret'), $request, $response, $banner->getCreativeSha1(), $banner->getModifyTime());
        
        $response->setCallback(function () use ($response, $banner, $isIECompat) {
            if ($isIECompat) {
                $headers = [];
                foreach ($response->headers->allPreserveCase() as $name => $value) {
                    if (strpos($name, "X-") === 0) {
                        $headers[] = "$name:" . implode(",", $value);
                    }
                }
                echo implode("\n", $headers) . "\n\n";
                echo base64_encode($banner->getCreativeContents());
            } else {
                echo $banner->getCreativeContents();
            }
        });
        
        $logIp = bin2hex(inet_pton($request->getClientIp()));
        $cid = Utils::createTrackingId($this->getParameter('secret'));
        
        
        $log = new EventLog();
        $log->setPublisherEventId(0);
        $log->setBannerId($banner->getId());
        $log->setCid(Utils::getRawTrackingId($cid));
        $log->setTid(Utils::getRawTrackingId($tid));
        $log->setIp($logIp);
        $log->setEventType("request");
        
        $em = $this->getDoctrine()->getManager();
        $em->persist($log);
        $em->flush();
          
        $response->headers->set('X-Adshares-Cid', $cid);
        $response->headers->set('X-Adshares-Lid', $log->getId());
        
        if (! $response->isNotModified($request)) {
            $response->headers->set('Content-Type', ($isIECompat ? 'text/base64,' : '') . $mime);
        }
        return $response;
    }
    
    /**
     * @Route("/demand/view.js")
     */
    public function viewScriptAction(Request $request)
    {
        $params = [json_encode($request->getSchemeAndHttpHost())];
        
        $jsPath = $this->get('kernel')->getEnvironment() == 'dev' ? './-/view.min.js' : './-/view.x.js';
        $response = new StreamedResponse();
        $response->setCallback(function () use ($jsPath, $request, $params) {
            echo str_replace([
                "'{{ ORIGIN }}'",
            ], $params, file_get_contents($jsPath));
        });
            
        $response->headers->set('Content-Type', 'text/javascript');
        
        $response->setCache(array(
            'etag' => md5(md5_file($jsPath) . implode(':', $params)),
            'last_modified' => new \DateTime('@' . filemtime($jsPath)),
            'max_age' => 3600 * 24 * 30,
            's_maxage' => 3600 * 24 * 30,
            'private' => false,
            'public' => true
        ));
        
        if (! $response->isNotModified($request)) {
        }
        return $response;
    }
    
    /**
     * @Route("/click/{id}", name="log_click", methods={"GET"}, requirements={"id": "\d+"})
     *
     */
    public function clickAction(Request $request, $id)
    {
        $banner = Banner::getRepository($this->getDoctrine()->getManager())->find($id);
        if (! $banner) {
            throw new NotFoundHttpException();
        }
        
        $campaign = Campaign::getRepository($this->getDoctrine()->getManager())->find($banner->getCampaignId());
        if (! $campaign) {
            throw new NotFoundHttpException();
        }
        
        $url = $campaign->getLandingUrl();
        $logIp = bin2hex(inet_pton($request->getClientIp()));
        
        $cid = Utils::getRawTrackingId($request->query->get('cid'));
        $pid = $request->query->get('pid');
        $tid = Utils::getRawTrackingId($request->cookies->get('tid')) ?: $logIp;
        $payTo = $request->query->get('pto');
        
        $em = $this->getDoctrine()->getManager();
        $log = new EventLog();
        $log->setCid($cid);
        $log->setPublisherEventId($pid);
        $log->setBannerId($id);
        $log->setTid($tid);
        $log->setIp($logIp);
        $log->setPayTo($payTo);
        $log->setEventType("click");
        //         $log->setTheirContext(Utils::getImpressionContext($this->container, $request));
        
        $em->persist($log);
        $em->flush();
        
        $adpayService = $this->container->has('adpay') ? $this->container->get('adpay') : null;
        $adpayService instanceof Adpay;
        
        if ($adpayService) {
            $adpayService->addEvents([
                $log->getAdpayJson(),
            ]);
        }
        
        $response = new Response($url);
        
        // last click id will be used to track conversions
        $response->headers->setCookie(new Cookie('cid', $request->query->get('cid'), new \DateTime('+ 1 month')));
        
        $response->setContent(sprintf('<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8" />
        <meta http-equiv="refresh" content="5;url=%1$s" />
            
        <title>Redirecting to %1$s</title>
    </head>
    <body>
        Redirecting to <a href="%1$s">%1$s</a>.
    </body>
</html>', htmlspecialchars($url, ENT_QUOTES, 'UTF-8')));
        
        return $response;
    }
    
    /**
     * @Route("/view/{id}", name="log_view", methods={"GET"}, requirements={"id": "\d+"})
     *
     */
    public function viewAction(Request $request, $id)
    {
        if ($request->query->get('r')) {
            $url = $request->query->get('r');
            $request->query->remove('r');
        } else {
            $url = '';
        }
        $logIp = bin2hex(inet_pton($request->getClientIp()));
        
        $cid = Utils::getRawTrackingId($request->query->get('cid'));
        $pid = $request->query->get('pid');
        $tid = Utils::getRawTrackingId($request->cookies->get('tid')) ?: $logIp;
        $payTo = $request->query->get('pto');
        
        $keywords = json_decode(Utils::UrlSafeBase64Decode($request->query->get('k')), true);
        
        $em = $this->getDoctrine()->getManager();
        
        $lid = $request->query->get('lid');
        if ($lid) {
            $log = EventLog::getRepository($em)->find($lid);
        } else {
            $log = null;
        }
        if (!$log) {
            $log = new EventLog();
        }
        $log->setPublisherEventId($pid);
        $log->setCid($cid);
        $log->setBannerId($id);
        $log->setPayTo($payTo);
        $log->setTid($tid);
        $log->setIp($logIp);
        $log->setTheirContext(Utils::getImpressionContext($this->container, $request));
        $log->setEventType("view");
        $log->setTheirUserdata($keywords);
        
        try {
            $em->persist($log);
            $em->flush();
        } catch (\Exception $e) {
        }
        
        
        $aduser_endpoint = $this->container->getParameter('aduser_endpoint');
        if ($aduser_endpoint) {
            $router = $this->container->get('router');
            assert($router instanceof Router);
            
            $iid = $request->query->get('iid') ?: Utils::createTrackingId($this->getParameter('secret'));
            $backUrl = $router->generate('log_keywords', [
                'iid' => $iid,
                'log_id' => $log->getId(),
                'r' => $url
            ], UrlGeneratorInterface::ABSOLUTE_URL);
            
            $response = new RedirectResponse($aduser_endpoint . '/setimg/' . $iid . '?r='. Utils::UrlSafeBase64Encode($backUrl));
        } else {
            $response = new Response();
            
            //transparent 1px gif
            $response->setContent(base64_decode('R0lGODlhAQABAIABAP///wAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=='));
            $response->headers->set('Content-Type', 'image/gif');
            
            $adpayService = $this->container->has('adpay') ? $this->container->get('adpay') : null;
            $adpayService instanceof Adpay;
            
            if ($adpayService) {
                $adpayService->addEvents([
                    $log->getAdpayJson(),
                ]);
            }
        }
        
        return $response;
    }
    
    
    /**
     * @Route("/keywords/{log_id}", name="log_keywords", methods={"GET"})
     *
     */
    public function keywordsAction(Request $request, $log_id)
    {
        $url = Utils::UrlSafeBase64Decode($request->query->get('r'));

        // GET kewords from aduser
        $impressionId = $request->query->get('iid');
        $aduser_endpoint = $this->container->getParameter('aduser_endpoint');
        if ($aduser_endpoint && $impressionId) {
            $userdata = json_decode(file_get_contents("{$aduser_endpoint}/get/{$impressionId}"), true);
        } else {
            $userdata = [];
        }
        
        $em = $this->getDoctrine()->getManager();
        $log = EventLog::getRepository($em)->find($log_id);
        $log instanceof EventLog;
        if ($log) {
            $log->setOurUserdata($userdata['keywords']);
            $log->setHumanScore($userdata['human_score']);
            $log->setUserId($userdata['user_id']);
            $em->persist($log);
            $em->flush();
        }
        
        $url = Utils::addUrlParameter($url, 's', parse_url($aduser_endpoint, PHP_URL_HOST));
        $url = Utils::addUrlParameter($url, 'k', Utils::UrlSafeBase64Encode(json_encode($userdata['keywords'])));
        
        $response = new RedirectResponse($url);
        
        $adpayService = $this->container->has('adpay') ? $this->container->get('adpay') : null;
        $adpayService instanceof Adpay;
        
        if ($adpayService && $log->getOurContext() && $log->getUserId()) {
            $adpayService->addEvents([
                $log->getAdpayJson(),
            ]);
        }
        
        return $response;
    }
    
    /**
     * @Route("/context/{log_id}", name="log_context", methods={"GET"})
     *
     */
    public function contextAction(Request $request, $log_id)
    {
        $keywords = json_decode(Utils::UrlSafeBase64Decode($request->query->get('k')), true);
        
        $context = Utils::getImpressionContext($this->container, $request, $keywords);
        
        $em = $this->getDoctrine()->getManager();
        $log = EventLog::getRepository($em)->find($log_id);
        $log instanceof EventLog;
        if ($log) {
            $log->setOurContext($context);
            $em->flush();
        }
        
        $response = new Response();
        //transparent 1px gif
        $response->setContent(base64_decode('R0lGODlhAQABAIABAP///wAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=='));
        $response->headers->set('Content-Type', 'image/gif');
        
        $adpayService = $this->container->has('adpay') ? $this->container->get('adpay') : null;
        $adpayService instanceof Adpay;
        
        if ($adpayService && $log->getOurContext() && $log->getUserId()) {
            $adpayService->addEvents([
                $log->getAdpayJson(),
            ]);
        }
        
        return $response;
    }
}
