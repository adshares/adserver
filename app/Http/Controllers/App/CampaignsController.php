<?php

namespace Adshares\Adserver\Http\Controllers\App;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Adshares\Adserver\Models\Campaign;

class CampaignsController extends AppController
{
    public function add(Request $request)
    {
        $this->validateRequestObject($request, 'campaign', Campaign::$rules);
        $campaign = Campaign::create($request->input('campaign'));
        $campaign->save();

        $reqObj = $request->input('campaign.targeting.require');
        if (null != $reqObj) {
            foreach (array_keys($reqObj) as $key) {
                $value = $reqObj[$key];
                $campaign->campaignRequires()->create(['key' => $key, 'value' => $value]);
            }
        }

        $reqObj = $request->input('site.targeting.exclude');
        if (null != $reqObj) {
            foreach (array_keys($reqObj) as $key) {
                $value = $reqObj[$key];
                $campaign->campaignExcludes()->create(['key' => $key, 'value' => $value]);
            }
        }

        $response = self::json(compact('campaign'), 201);
        $response->header('Location', route('app.campaign.read', ['campaign' => $campaign]));

        return $response;
    }

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

    public function read(Request $request, $campaignId)
    {
        // TODO check privileges
        $campaign = Campaign::with([
            'campaignExcludes' => function ($query) {
                /* @var $query Builder */
                $query->whereNull('deleted_at');
            },
            'campaignRequires' => function ($query) {
                /* @var $query Builder */
                $query->whereNull('deleted_at');
            },
        ])->whereNull('deleted_at')->findOrFail($campaignId);

        return self::json(compact('campaign'));
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     *
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
