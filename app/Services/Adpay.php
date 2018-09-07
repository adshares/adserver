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

namespace Adshares\Services;

use JsonRPC\Client;

/**
 *
 * Wrapper class used to interact with adpay service
 *
 */
class Adpay
{
    private $endpointUrl;
    private $rpcClient;
    
    public function __construct($endpoint, $debug)
    {
        $this->endpointUrl = $endpoint;
        $this->rpcClient = new Client($this->endpointUrl);
        $this->rpcClient->getHttpClient()->withTimeout(10);
        if ($debug) {
            $this->rpcClient->getHttpClient()->withDebug();
        }
    }
    
    public function getPayments($hour_timestamp)
    {
        return $this->rpcClient->execute("get_payments", [['timestamp' => $hour_timestamp]]);
        /*
-> { 'timestamp':timestamp }
<- {
    'payments':[
        {'event_id':event_id1, 'amount':pay_amount1},
        {'event_id':event_id2, 'amount':pay_amount2},
     ]
}

         */
    }
    
    public function testGetPayments($hour_timestamp)
    {
        return $this->rpcClient->execute("test_get_payments", [['timestamp' => $hour_timestamp]]);
    }
    
    public function addEvents(array $events)
    {
        return $this->rpcClient->execute("add_events", $events);
        
        /*
-> [
    {
        "event_id": 1029,
        "banner_id": "24309404525f4125a16d255c49681129",
        "keywords": {
            "tid": "ca2581794c8b30890d18a33b89984959",
            "screen_width": 1920,
            "screen_height": 1080,
            "inframe": "no",
            "host": "website.priv",
            "path": "website.priv\/",
            "context_lorem ipsum": 1,
            "context_lipsum": 1,
            "context_lorem": 1,
            "context_ipsum": 1,
            "context_text": 1,
            "context_generate": 1,
            "context_generator": 1,
            "context_facts": 1,
            "context_information": 1,
            "context_what": 1,
            "locale": "pl",
            "browser_name": "chrome",
            "browser_version": "60.0",
            "platform_name": "win",
            "platform_version": "10",
            "device_type": "desktop",
            "geo_continent_code": "EU",
            "geo_country_code": "DE",
            "geo_country_code3": "DEU",
            "geo_country_name": "Germany",
            "geo_region": "05",
            "geo_city": "Frankfurt Am Main",
            "geo_postal_code": "60314",
            "geo_latitude": 50.11370086669922,
            "geo_longitude": 8.711899757385254
        },
        "paid_amount": 0,
        "user_id": "ca2581794c8b30890d18a33b89984959",
        "publisher_id": "asdasd"
    }
]
<- {'result':True}
         */
    }
    
    public function addCampaigns(array $campaings)
    {
        return $this->rpcClient->execute("campaign_update", $campaings);
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
        return $this->rpcClient->execute("campaign_delete", $campaignIds);
//         print_r($campaignIds);
//         exit;
        // [1, 2, 3, 4, 5]
    }
}
