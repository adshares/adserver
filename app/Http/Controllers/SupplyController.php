<?php
namespace Adshares\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Adshares\Helper\Utils;
use Adshares\Supply\BannerFinder;
use Adshares\Coin\Api;

use Adshares\Entity\NetworkCampaign;
use Adshares\Entity\NetworkEventLog;
use Adshares\Services\Adselect;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * HTTP api that is used by supply adserver to display banners and log relevant events
 *
 */
class SupplyController extends Controller
{

    /**
     * @Route("/supply")
     */
    public function indexAction(Request $request)
    {
        return new Response('supply');
    }

    /**
     * Finds banners requested by website
     *
     * @Route("/supply/find", methods={"GET", "POST", "OPTIONS"})
     */
    public function indexFind(Request $request)
    {
        $response = new Response();
        
        if ($request->headers->has("Origin")) {
            $response->headers->set("Access-Control-Allow-Origin", $request->headers->get("Origin"));
            $response->headers->set("Access-Control-Allow-Credentials", "true");
            $response->headers->set("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
        }
        
        if ($request->getRealMethod() == 'GET') {
            $data = $request->getQueryString();
        } elseif ($request->getRealMethod() == 'POST') {
            $data = $request->getContent();
        } elseif ($request->getRealMethod() == 'OPTIONS') {
            $response->setStatusCode(204);
            $response->headers->set('Access-Control-Max-Age', 1728000);
            return $response;
        }
        
        $decoded = Utils::decodeZones($data);
//         print_r($decoded);exit;
        $zones = $decoded['zones'];
        
        $tid = Utils::attachTrackingCookie($this->getParameter('secret'), $request, $response, "", new \DateTime());

        // use adselect here
        $context = Utils::getImpressionContext($this->container, $request, $data);
        
        $impressionId = $decoded['page']['iid'];
        if ($impressionId) {
            $aduser_endpoint = $this->container->getParameter('aduser_endpoint');
            if ($aduser_endpoint) {
                $userdata = (array)json_decode(file_get_contents("{$aduser_endpoint}/get/{$impressionId}"), true);
            } else {
                $userdata = [];
            }
        }
        
        $keywords = array_merge($context, $userdata);
        
        $banners = BannerFinder::getBestBanners($this->container, $this->getDoctrine()->getManager(), $zones, $keywords);
        
        foreach ($banners as &$banner) {
            if ($banner) {
                $banner['pay_to'] = Api::normalizeAddress($this->container->getParameter('adshares_address'));
            }
        }
        
        
        $response->setContent(json_encode($banners, JSON_PRETTY_PRINT));
        return $response;
    }

    /**
     * @Route("/supply/find.js")
     */
    public function findScriptAction(Request $request)
    {
        $aduser_endpoint = $this->container->getParameter('aduser_endpoint');
        
        $params = [json_encode($request->getSchemeAndHttpHost()), json_encode($aduser_endpoint)];
        
        $jsPath = $this->get('kernel')->getEnvironment() == 'dev' ? './-/find.min.js' : './-/find.x.js';
        $response = new StreamedResponse();
        $response->setCallback(function () use ($jsPath, $request, $params) {
            echo str_replace([
                "'{{ ORIGIN }}'",
                "'{{ ADUSER }}'",
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
     * @Route("/nclick/{id}", name="log_network_click", methods={"GET"}, requirements={"id": "[0-9a-f]+"})
     *
     */
    public function networkClickAction(Request $request, $id)
    {
        if ($request->query->get('r')) {
            $url = Utils::UrlSafeBase64Decode($request->query->get('r'));
            $request->query->remove('r');
        } else {
            $banner = NetworkCampaign::getRepository($this->getDoctrine()->getManager())->findOneBy([
                'uuid' => $id
            ]);
            
            if (! $banner) {
                throw new NotFoundHttpException();
            }
            
            $url = $banner->getClickUrl();
        }
        $qString = http_build_query($request->query->all());
        if ($qString) {
            $qPos = strpos($url, '?');
            
            if ($qPos === false) {
                $url .= '?' . $qString;
            } elseif ($qPos == strlen($url) - 1) {
                $url .= $qString;
            } else {
                $url .= '&' . $qString;
            }
        }
        
        $logIp = bin2hex(inet_pton($request->getClientIp()));
        
        
        $cid = Utils::getRawTrackingId($request->query->get('cid'));
        $tid = Utils::getRawTrackingId($request->cookies->get('tid')) ?: $logIp;
        // TODO get user / website / zone
        $payFrom = $request->query->get('pfr');
        
        $log = new NetworkEventLog();
        $log->setCid($cid);
        $log->setBannerId($id);
        $log->setPayFrom($payFrom);
        $log->setTid($tid);
        $log->setIp($logIp);
        $log->setEventType("click");
        //         $log->setContext(Utils::getImpressionContext($this->container, $request));
        
        $em = $this->getDoctrine()->getManager();
        $em->persist($log);
        $em->flush();
        
        $url = Utils::addUrlParameter($url, 'pid', $log->getId());
        
        $adselectService = $this->container->has('adselect') ? $this->container->get('adselect') : null;
        $adselectService instanceof Adselect;
        
        if ($adselectService) {
            $adselectService->addImpressions([
                $log->getAdselectJson(),
            ]);
        }
        
        $response = new RedirectResponse($url);
        return $response;
    }
    
    /**
     * @Route("/nview/{id}", name="log_network_view", methods={"GET"}, requirements={"id": "[0-9a-f]+"})
     *
     */
    public function networkViewAction(Request $request, $id)
    {
        if ($request->query->get('r')) {
            $url = Utils::UrlSafeBase64Decode($request->query->get('r'));
            $request->query->remove('r');
        } else {
            $banner = NetworkCampaign::getRepository($this->getDoctrine()->getManager())->findOneBy([
                'uuid' => $id
            ]);
            
            if (! $banner) {
                throw new NotFoundHttpException();
            }
            
            $url = $banner->getViewUrl();
        }
        $qString = http_build_query($request->query->all());
        if ($qString) {
            $qPos = strpos($url, '?');
            
            if ($qPos === false) {
                $url .= '?' . $qString;
            } elseif ($qPos == strlen($url) - 1) {
                $url .= $qString;
            } else {
                $url .= '&' . $qString;
            }
        }
        
        $logIp = bin2hex(inet_pton($request->getClientIp()));
        
        $cid = Utils::getRawTrackingId($request->query->get('cid'));
        $tid = Utils::getRawTrackingId($request->cookies->get('tid')) ?: $logIp;
        $payFrom = $request->query->get('pfr');
        
        $log = new NetworkEventLog();
        $log->setCid($cid);
        $log->setBannerId($id);
        $log->setPayFrom($payFrom);
        $log->setTid($tid);
        $log->setIp($logIp);
        $log->setEventType("view");
        $log->setContext(Utils::getImpressionContext($this->container, $request));
        
        // GET kewords from aduser
        $impressionId = $request->query->get('iid');
        $aduser_endpoint = $this->container->getParameter('aduser_endpoint');
        if ($aduser_endpoint && $impressionId) {
            $userdata = json_decode(file_get_contents("{$aduser_endpoint}/get/{$impressionId}"), true);
        } else {
            $userdata = [];
        }
        $log->setOurUserdata($userdata['keywords']);
        $log->setHumanScore($userdata['human_score']);
        $log->setUserId($userdata['user_id']);
        
        
        // make sure db error wont stop redirection
//         try {
            $em = $this->getDoctrine()->getManager();
            $em->persist($log);
            $em->flush();
            
            $adselectService = $this->container->has('adselect') ? $this->container->get('adselect') : null;
            $adselectService instanceof Adselect;
            
        if ($adselectService) {
            $adselectService->addImpressions([
                $log->getAdselectJson(),
            ]);
        }
            
            
            $router = $this->container->get('router');
            assert($router instanceof Router);
            
            $backUrl = $router->generate('log_network_keywords', [
                'log_id' => $log->getId(),
            ], UrlGeneratorInterface::ABSOLUTE_URL);
            
            $url = Utils::addUrlParameter($url, 'pid', $log->getId());
            $url = Utils::addUrlParameter($url, 'k', Utils::UrlSafeBase64Encode(json_encode($log->getOurUserdata())));
            $url = Utils::addUrlParameter($url, 'r', Utils::UrlSafeBase64Encode($backUrl));
            
//         } catch (\Exception $e) {}
        
        $response = new RedirectResponse($url);
        
        return $response;
    }
    
    /**
     * @Route("/nkeywords/{log_id}", name="log_network_keywords", methods={"GET"})
     *
     */
    public function networkKeywordsAction(Request $request, $log_id)
    {
        $source = $request->query->get('s');
        $keywords = json_decode(Utils::UrlSafeBase64Decode($request->query->get('k')), true);
        
        $em = $this->getDoctrine()->getManager();
        $log = NetworkEventLog::getRepository($em)->find($log_id);
        $log instanceof NetworkEventLog;
        if ($log) {
            $log->setTheirUserdata($keywords);
            $em->persist($log);
            $em->flush();
        }
        //         $keywords = print_r($keywords, 1);
        //         $response = new Response("nKeywords logId={$log_id} source={$source} keywords={$keywords}");
        //         return $response;
        
        $response = new Response();
        //transparent 1px gif
        $response->setContent(base64_decode('R0lGODlhAQABAIABAP///wAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=='));
        $response->headers->set('Content-Type', 'image/gif');
        return $response;
    }
}
