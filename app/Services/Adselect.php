<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute it and/or modify it
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
 * along with AdServer.  If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Services;

use JsonRPC\Client;

/**
 * Wrapper class used to interact with adselect service.
 */
class Adselect
{
    private $endpointUrl;
    private $rpcClient;

    public function __construct($endpointUrl, $debug = false)
    {
        $this->endpointUrl = $endpointUrl;
        $this->rpcClient = new Client($this->endpointUrl);
        $this->rpcClient->getHttpClient()->withTimeout(1);
        if ($debug) {
            $this->rpcClient->getHttpClient()->withDebug();
        }
    }

    public function getStatus()
    {
        /*
         {
            'last_event_id': 0
         }
         */
    }

    public function addImpressions(array $events)
    {
        return $this->rpcClient->execute('impression_add', $events);
//         echo json_encode($events, JSON_PRETTY_PRINT);
//                 exit;
        /*
        {
            'event_id': 0
            'banner_id': '',
            'impression_keywords': KEYWORDS,
            'paid_amount': 0
            'userid': ''
        }
        */
    }

    public function getBanners(array $requests)
    {
        return $this->rpcClient->execute('banner_select', $requests);
        /*
         {
            'request_id': ''
            'banner_filters':{
                'require':[{
                    'keyword':'',
                    'filter': FILTER
                },],
                'exclude':[{
                    'keyword':'',
                    'filter': FILTER
                },]
            },
            'userid':'',
            'impression_keywords': KEYWORDS
        },
         */

//         echo json_encode($requests, JSON_PRETTY_PRINT);
//         exit;

//         try {
//             return $this->rpcClient->getBanners($requests);
//         } catch(\Exception $e) {
//             return [];
//         }

        $responses = [];

        foreach ($requests as $request) {
            $responses[] = [
                'request_id' => $request['request_id'],
                'banner_id' => $request['request_id'] + 1,
            ];
        }

        return $responses;
    }

    public function addCampaigns(array $campaings)
    {
        return $this->rpcClient->execute('campaign_update', $campaings);
//         echo json_encode($campaings, JSON_PRETTY_PRINT);
//         exit;
        /*
        {
            'campaign_id': 0,
            'impression_filters':{
                'require': [{
                    'keyword': '',
                    'filter': FILTER
                }],
                'exclude':[{
                    'keyword':'',
                    'filter': FILTER
                }],
            }
            'campaign_keywords': KEYWORDS
            'banners': [
                {
                    'id':'',
                    'banner_keywords': KEYWORDS,
                },
            ]
        }
         */
    }

    public function deleteCampaigns(array $campaignIds)
    {
        return $this->rpcClient->execute('campaign_delete', $campaignIds);
//         print_r($campaignIds);
//         exit;
        // [1, 2, 3, 4, 5]
    }
}
