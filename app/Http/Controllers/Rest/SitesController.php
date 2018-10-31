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
use Adshares\Adserver\Http\Controllers\Simulator;
use Adshares\Adserver\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class SitesController extends Controller
{
    public function add(Request $request)
    {
        $this->validateRequestObject($request, 'site', Site::$rules);

        /** @var Site|Builder $site */
        $site = Site::create($request->input('site'));
        $site->user_id = Auth::user()->id;
        $site->site_requires = $request->input('site.filtering.requires');
        $site->site_excludes = $request->input('site.filtering.excludes');
        $site->save();

        //needs to be removed after front-end refactor
        $zones = $this->mapAdUnitsToZoneModel($request->input('site.ad_units'));

        $site->zones()->createMany($zones);

        $response = self::json([], 201);
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

        return self::json(['message' => 'Successfully edited'], Response::HTTP_NO_CONTENT);
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
     * @deprecated
     */
    private function mapAdUnitsToZoneModel($adUnits): array
    {
        $adUnits = array_map(function ($zone) {
            $zone['name'] = $zone['short_headline'];
            unset($zone['short_headline']);

            $size = Simulator::getZoneTypes()[$zone['size']['size']];
            $zone['width'] = $size['width'];
            $zone['height'] = $size['height'];

            return $zone;
        }, $adUnits);

        return $adUnits;
    }
}
