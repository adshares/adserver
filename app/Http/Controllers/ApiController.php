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
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Utilities\AdsUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class ApiController extends Controller
{
    private $campaignRepository;

    public function __construct(CampaignRepository $campaignRepository)
    {
        $this->campaignRepository = $campaignRepository;
    }

    public function adsharesInventoryList()
    {
        $campaigns = [];
        foreach ($this->campaignRepository->find() as $i => $campaign) {

            $banners = [];

            foreach ($campaign->ads as $banner) {
                $banners[] = [
                    'uuid' => $banner->uuid,
                    'width' => $banner->width,
                    'height' => $banner->height,
                    'type' => $banner->type,
                    'size' => $banner->size,
                    'serve_url' => $banner->serve_url,
                    'click_url' => $banner->click_url,
                    'view_url' => $banner->view_url,
                ];
            }

            $campaigns[] = [
                'id' => $campaign->id,
                'uuid' => $campaign->uuid,
                'user_id' => $campaign->user_id,
                'landing_url' => $campaign->landing_url,
                'date_start' => $campaign->date_start,
                'date_end' => $campaign->date_end,
                'max_cpc' => $campaign->max_cpc,
                'max_cpm' => $campaign->max_cpm,
                'budget' => $campaign->budget,
                'banners' => $banners,
                'adshares_address' => AdsUtils::normalizeAddress(config('app.adshares_address')),
            ];
        }

        return Response::json(['campaigns' => $campaigns], 200, [], JSON_PRETTY_PRINT);
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
