<?php

namespace Adshares\Adserver\Http\Controllers\App;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Adshares\Adserver\Models\Campaign;

class CampaignsController extends AppController
{
    public function browse(Request $request)
    {
        // TODO check privileges
        $campaigns = Campaign::with([
            'campaignExcludes' => function ($query) {
                /* @var $query Builder */
                $query->whereNull('deleted_at');
            },
            'campaignRequires' => function ($query) {
                /* @var $query Builder */
                $query->whereNull('deleted_at');
            },
        ])->whereNull('deleted_at')->get();

        return self::json($campaigns);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Adshares\Adserver\Exceptions\JsonResponseException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function targeting(Request $request)
    {
        //@TODO: create function data
        $siteTargeting = [];
        $response = self::json($siteTargeting, 200);

        return $response;
    }

}
