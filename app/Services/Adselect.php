<?php
namespace Adshares\Adserver\Services;

use JsonRPC\Client;

/**
 *
 * Wrapper class used to interact with adselect service
 *
 */
class Adselect
{
    private $endpointUrl;
    private $rpcClient;

    public function __construct($endpoint, $debug)
    {
        $this->endpointUrl = $endpoint;
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
        return $this->rpcClient->execute("impression_add", $events);
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
        return $this->rpcClient->execute("banner_select", $requests);
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
                'banner_id' => $request['request_id']+1
            ];
        }

        return $responses;
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
