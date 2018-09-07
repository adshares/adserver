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

use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Utilities\AdsUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

/**
 * API commands that are used to communicate and share informations between adserver isntances.
 */
class ApiController extends Controller
{
    public function adsharesInventoryList(Request $request)
    {
        $campaigns = [];
        foreach (Campaign::getWithReferences(false) as $i => $campaign) {
            $campaigns[$i] = $campaign->toArray();
            $campaigns[$i]['adshares_address'] = AdsUtils::normalizeAddress(config('app.adshares_address'));
        }

        return Response::json(['campaigns' => $campaigns], 200, array(), JSON_PRETTY_PRINT);
    }

    public function adsharesTransactionReport(Request $request, $tx_id, $pay_to)
    {
        // TODO : convert 2 laravel

        $em = $this->getDoctrine()->getManager();
        assert($em instanceof \Doctrine\ORM\EntityManager);

        $paymentId = Payment::getRepository($em)->findOneBy(['tx_id' => $tx_id]);

        if (!$paymentId) {
            $response = null;
        } else {
            $response = [];

            $events = $em->createQuery(
                "SELECT
                    e.publisher_event_id, e.paid_amount, e.timestamp
                  FROM
                    Adshares\Entity\EventLog e
                  WHERE
                    e.payment_id = :payment_id
                  AND
                    e.pay_to = :pay_to"
            )->iterate(['payment_id' => $paymentId, 'pay_to' => $pay_to], Query::HYDRATE_SCALAR);

            $minTime = PHP_INT_MAX;
            $maxTime = PHP_INT_MIN;
            foreach ($events as $event) {
//                 $response[] = $event[0];
                $response[$pay_to]['events'][$event[0]['publisher_event_id']] = $event[0]['paid_amount'];
                $minTime = min($event[0]['timestamp'], $minTime);
                $maxTime = max($event[0]['timestamp'], $maxTime);
            }

            if (PHP_INT_MIN != $maxTime && PHP_INT_MAX != $minTime) {
                $response[$pay_to]['time_start'] = $minTime;
                $response[$pay_to]['time_end'] = $maxTime;
            }
        }
        $x = new Response();
        $x->headers->set('Content-Type', 'text/json');
        $x->setContent(json_encode($response));

        return $x;
    }
}
