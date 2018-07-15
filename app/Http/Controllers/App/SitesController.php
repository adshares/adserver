<?php

namespace Adshares\Adserver\Http\Controllers\App;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Adshares\Adserver\Models\Site;

class SitesController extends AppController
{
    public function add(Request $request)
    {
        $this->validateRequestObject($request, 'site', Site::$rules);
        $site = Site::create($request->input('site'));
        $site->save();

        $reqObj = $request->input('site.targeting.require');
        if (null != $reqObj) {
            foreach (array_keys($reqObj) as $key) {
                $value = $reqObj[$key];
                $site->siteRequires()->create(['key' => $key, 'value' => $value]);
            }
        }

        $reqObj = $request->input('site.targeting.exclude');
        if (null != $reqObj) {
            foreach (array_keys($reqObj) as $key) {
                $value = $reqObj[$key];
                $site->siteExcludes()->create(['key' => $key, 'value' => $value]);
            }
        }

        $response = self::json(compact('site'), 201);
        $response->header('Location', route('app.sites.read', ['site' => $site]));

        return $response;
    }

    public function browse(Request $request)
    {
        // TODO check privileges
        $sites = Site::with([
            'siteExcludes' => function ($query) {
                /* @var $query Builder */
                $query->whereNull('deleted_at');
            },
            'siteRequires' => function ($query) {
                /* @var $query Builder */
                $query->whereNull('deleted_at');
            },
        ])->whereNull('deleted_at')->get();

        return self::json($sites);
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Adshares\Adserver\Exceptions\JsonResponseException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function count(Request $request)
    {
        //@TODO: create function data
        $siteCount = [
            'totalEarnings' => 0,
            'totalClicks' => 0,
            'totalImpressions' => 0,
            'averagePageRPM' => 0,
            'averageCPC' => 0,
        ];
        $response = self::json($siteCount, 200);

        return $response;
    }

    public function edit(Request $request, $site_id)
    {
        $this->validateRequestObject($request, 'site', array_intersect_key(Site::$rules, $request->input('site')));

        // TODO check privileges
        $site = Site::whereNull('deleted_at')->findOrFail($site_id);
        $site->update($request->input('site'));

        return self::json(['message' => 'Successfully edited'], 200);
    }

    public function delete(Request $request, $site_id)
    {
        // TODO check privileges
        $site = Site::whereNull('deleted_at')->findOrFail($site_id);
        $site->deleted_at = new \DateTime();
        $site->save();

        return self::json(['message' => 'Successfully deleted'], 200);
    }

    public function read(Request $request, $site_id)
    {
        // TODO check privileges
        $site = Site::with([
            'siteExcludes' => function ($query) {
                /* @var $query Builder */
                $query->whereNull('deleted_at');
            },
            'siteRequires' => function ($query) {
                /* @var $query Builder */
                $query->whereNull('deleted_at');
            },
        ])->whereNull('deleted_at')->findOrFail($site_id);

        return self::json(compact('site'));
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
        return self::json(json_decode('[
          {
            "label": "Creative type",
            "key":"category",
            "values": [
              {"label": "Audio Ad (Auto-Play)", "value": "1"},
              {"label": "Audio Ad (User Initiated)", "value": "2"},
              {"label": "In-Banner Video Ad (Auto-Play)", "value": "6"},
              {"label": "In-Banner Video Ad (User Initiated)", "value": "7"},
              {"label": "Provocative or Suggestive Imagery", "value": "9"},
              {"label": "Shaky, Flashing, Flickering, Extreme Animation, Smileys", "value": "10"},
              {"label": "Surveys", "value": "11"},
              {"label": "Text Only", "value": "12"},
              {"label": "User Interactive (e.g., Embedded Games)", "value": "13"},
              {"label": "Windows Dialog or Alert Style", "value": "14"},
              {"label": "Has Audio On/Off Button", "value": "15"}
            ],
            "value_type": "string",
            "allow_input": true
          },
          {
            "label": "Language",
            "key": "lang",
            "values": [
              {"label": "Polish", "value": "pl"},
              {"label": "English", "value": "en"},
              {"label": "Italian", "value": "it"},
              {"label": "Japanese", "value": "jp"}
            ],
            "value_type": "string",
            "allow_input": false
          },
          {
            "label": "Screen",
            "key":"screen",
            "children": [
              {
                "label": "Width",
                "key": "width",
                "values": [
                  {"label": "1200 or more", "value": "<1200,>"},
                  {"label": "between 1200 and 1800", "value": "<1200,1800>"}
                ],
                "value_type": "number",
                "allow_input": true
              },
              {
                "label": "Height",
                "key": "height",
                "values": [
                  {"label": "900 or more", "value": "<900,>"},
                  {"label": "between 200 and 300", "value": "<200,300>"}
                ],
                "value_type": "number",
                "allow_input": true
              }
            ]
          },
          {
            "label": "Javascript support",
            "key": "js_enabled",
            "value_type": "boolean",
            "values": [
              {"label": "Yes", "value": "true"},
              {"label": "No", "value": "false"}
            ],
            "allow_input": false
          }
        ]'), 200);
    }
}
