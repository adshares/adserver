<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Utilities\AdsUtils;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ApiController extends Controller
{
    private $campaignRepository;

    public function __construct(CampaignRepository $campaignRepository)
    {
        $this->campaignRepository = $campaignRepository;
    }

    public function adsharesInventoryList(Request $request)
    {
        $licenceTxFee =  Config::getFee(Config::LICENCE_TX_FEE);
        $operatorTxFee = Config::getFee(Config::OPERATOR_TX_FEE);

        $campaigns = [];
        foreach ($this->campaignRepository->fetchActiveCampaigns() as $i => $campaign) {
            $banners = [];

            foreach ($campaign->ads as $banner) {
                $bannerArray = $banner->toArray();

                if (Banner::STATUS_ACTIVE != $bannerArray['status']) {
                    continue;
                }

                $banners[] = [
                    'id' => $bannerArray['uuid'],
                    'width' => $bannerArray['creative_width'],
                    'height' => $bannerArray['creative_height'],
                    'type' => $bannerArray['creative_type'],
                    'serve_url' => $this->changeHost($bannerArray['serve_url'], $request),
                    'click_url' => $this->changeHost($bannerArray['click_url'], $request),
                    'view_url' => $this->changeHost($bannerArray['view_url'], $request),
                ];
            }

            $campaigns[] = [
                'id' => $campaign->uuid,
                'publisher_id' => User::find($campaign->user_id)->uuid,
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

        return Response::json($campaigns, SymfonyResponse::HTTP_OK, [], JSON_PRETTY_PRINT);
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

    public function adsharesTransactionReport(Request $request, $tx_id, $pay_to)
    {
        // TODO : convert 2 laravel

//        $em = $this->getDoctrine()->getManager();
//        assert($em instanceof \Doctrine\ORM\EntityManager);
//
//        $paymentId = Payment::getRepository($em)->findOneBy(['tx_id' => $tx_id]);
//
//        if (!$paymentId) {
//            $response = null;
//        } else {
//            $response = [];
//
//            $events = $em->createQuery(
//                "SELECT
//                    e.publisher_event_id, e.paid_amount, e.timestamp
//                  FROM
//                    Adshares\Entity\EventLog e
//                  WHERE
//                    e.payment_id = :payment_id
//                  AND
//                    e.pay_to = :pay_to"
//            )->iterate(['payment_id' => $paymentId, 'pay_to' => $pay_to], Query::HYDRATE_SCALAR);
//
//            $minTime = PHP_INT_MAX;
//            $maxTime = PHP_INT_MIN;
//            foreach ($events as $event) {
////                 $response[] = $event[0];
//                $response[$pay_to]['events'][$event[0]['publisher_event_id']] = $event[0]['paid_amount'];
//                $minTime = min($event[0]['timestamp'], $minTime);
//                $maxTime = max($event[0]['timestamp'], $maxTime);
//            }
//
//            if (PHP_INT_MIN != $maxTime && PHP_INT_MAX != $minTime) {
//                $response[$pay_to]['time_start'] = $minTime;
//                $response[$pay_to]['time_end'] = $maxTime;
//            }
//        }
//        $x = new Response();
//        $x->headers->set('Content-Type', 'text/json');
//        $x->setContent(json_encode($response));
//
//        return $x;
    }
}
