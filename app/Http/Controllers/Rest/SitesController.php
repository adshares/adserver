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

namespace Adshares\Adserver\Http\Controllers\Rest;

use Adshares\Adserver\Http\Controllers\Controller;
use Adshares\Adserver\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class SitesController extends Controller
{
    public function add(Request $request)
    {
        $this->validateRequestObject($request, 'site', Site::$rules);

        $site = Site::create($request->input('site'));
        $site->user_id = Auth::user()->id;
        $site->site_requires = $request->input('site.targeting.requires');
        $site->site_excludes = $request->input('site.targeting.excludes');

        $site->save();

        $response = self::json(compact('site'), 201);
        $response->header('Location', route('app.sites.read', ['site' => $site]));

        return $response;
    }

    public function browse()
    {
        return self::json(Site::get()->toArray());
    }

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

        return self::json($siteCount, 200);
    }

    public function edit(Request $request, Site $site)
    {
        $this->validateRequestObject($request, 'site', array_intersect_key(Site::$rules, $request->input('site')));

        $site->update($request->input('site'));

        return self::json(['message' => 'Successfully edited'], 200);
    }

    public function delete(Site $site)
    {
        $site->delete();

        return self::json(['message' => 'Successfully deleted'], Response::HTTP_NO_CONTENT);
    }

    public function read(Site $site)
    {
        return self::json($site->toArray());
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
        return self::json(
            json_decode(
                '[
          {
            "id": "1",
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
            "id": "2",
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
            "id": "3",
            "label": "Browser",
            "key": "browser",
            "values": [
              {"label": "Firefox", "value": "firefox"},
              {"label": "Chrome", "value": "chrome"},
              {"label": "Safari", "value": "safari"},
              {"label": "Edge", "value": "edge"}
            ],
            "value_type": "string",
            "allow_input": false
          },  
          {
            "id": "4",
            "label": "Javascript support",
            "key": "js_enabled",
            "value_type": "boolean",
            "values": [
              {"label": "Yes", "value": "true"},
              {"label": "No", "value": "false"}
            ],
            "allow_input": false
          }
        ]'
            ),
            200
        );
    }

    public function banners(Request $request)
    {
        return self::json(
            json_decode(
                '[
          {
            "id": 1,
        "name": "Leaderboard",
        "type": "leaderboard",
        "size": 0,
        "tags": ["Desktop"]
      },
        {
            "id": 2,
        "name": "Large Rectangle",
        "type": "large-rectangle",
        "size": 3,
        "tags": ["Desktop"]
      },
        {
            "id": 3,
        "name": "Large Mobile Banner",
        "type": "large-mobile-banner",
        "size": 2,
        "tags": ["Desktop", "Mobile"]
      },
        {
            "id": 4,
        "name": "Large Rectangle",
        "type": "large-rectangle",
        "size": 3,
        "tags": ["Desktop"]
      },
        {
            "id": 5,
        "name": "Large Rectangle 2",
        "type": "large-rectangle",
        "size": ,
        "tags": ["Desktop"]
      }
        ]'
            ),
            200
        );
    }
}
