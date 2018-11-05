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
use Symfony\Component\HttpFoundation\JsonResponse;

class SitesController extends Controller
{
    public function add(Request $request): JsonResponse
    {
        $this->validateRequestObject($request, 'site', Site::$rules);
        $site = Site::create($request->input('site'));
        $site->user_id = Auth::user()->id;
        $site->save();

        return self::json([], Response::HTTP_CREATED)
            ->header('Location', route('app.sites.read', ['site' => $site]));
    }

    public function edit(Request $request, Site $site): JsonResponse
    {
        $input = $request->input('site');
        $this->validateRequestObject($request, 'site', array_intersect_key(Site::$rules, $input));

        $site->update($input);

        return self::json(['message' => 'Successfully edited'], Response::HTTP_NO_CONTENT);
    }

    public function browse(): JsonResponse
    {
        return self::json(Site::get());
    }

    public function count(): JsonResponse
    {
        $siteCount = [
            'totalEarnings' => 0,
            'totalClicks' => 0,
            'totalImpressions' => 0,
            'averagePageRPM' => 0,
            'averageCPC' => 0,
        ];

        return self::json($siteCount, 200);
    }

    public function delete(Site $site): JsonResponse
    {
        $site->delete();

        return self::json(['message' => 'Successfully deleted']);
    }

    public function read(Site $site): JsonResponse
    {
        return self::json($site);
    }
}
