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

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\Response\Site\SizesResponse;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Services\Supply\SiteClassificationUpdater;
use Adshares\Common\Exception\InvalidArgumentException;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\JsonResponse;

class SitesController extends Controller
{
    /** @var SiteClassificationUpdater */
    private $siteClassificationUpdater;
    
    public function __construct(SiteClassificationUpdater $siteClassificationUpdater)
    {
        $this->siteClassificationUpdater = $siteClassificationUpdater;
    }

    public function create(Request $request): JsonResponse
    {
        $this->validateRequestObject($request, 'site', Site::$rules);
        $input = $request->input('site');

        DB::beginTransaction();

        try {
            $site = Site::create($input);
            $site->user_id = Auth::user()->id;
            $site->save();
            $this->siteClassificationUpdater->addClassificationToFiltering($site);

            $site->zones()->createMany($request->input('site.ad_units'));
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }

        DB::commit();

        return self::json([], Response::HTTP_CREATED)->header('Location', route('app.sites.read', ['site' => $site]));
    }

    public function read(Site $site): JsonResponse
    {
        return self::json($this->processClassificationInFiltering($site));
    }

    private function processClassificationInFiltering(Site $site): array
    {
        $namespace = (string)config('app.classify_namespace');

        $siteArray = $site->toArray();
        $filtering = $siteArray['filtering'];

        $filtering['requires'] = array_filter(
            $filtering['requires'] ?: [],
            $this->filterOutHelperKeywords($namespace),
            ARRAY_FILTER_USE_KEY
        );
        $filtering['excludes'] = array_filter(
            $filtering['excludes'] ?: [],
            $this->filterOutHelperKeywords($namespace),
            ARRAY_FILTER_USE_KEY
        );

        $siteArray['filtering'] = $filtering;

        return $siteArray;
    }

    private function filterOutHelperKeywords(string $namespace): Closure
    {
        return function ($key) use ($namespace) {
            return $namespace !== $key && false === strpos($key, SiteClassificationUpdater::KEYWORD_CLASSIFIED);
        };
    }

    public function update(Request $request, Site $site): JsonResponse
    {
        $input = $request->input('site');
        $this->validateRequestObject($request, 'site', array_intersect_key(Site::$rules, $input));

        DB::beginTransaction();

        try {
            $site->fill($input);
            $site->push();
            $this->siteClassificationUpdater->addClassificationToFiltering($site);

            $inputZones = $this->processInputZones($site, Collection::make($request->input('site.ad_units')));

            $site->zones()->createMany($inputZones->all());
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }

        DB::commit();

        return self::json(['message' => 'Successfully edited']);
    }

    private function processInputZones(Site $site, Collection $inputZones)
    {
        foreach ($site->zones as $zone) {
            $zoneFromInput = $inputZones->firstWhere('id', $zone->id);
            if ($zoneFromInput) {
                $zone->update($zoneFromInput);
                $inputZones = $inputZones->reject(
                    function ($value) use ($zone) {
                        return (int)($value['id'] ?? "") === $zone->id;
                    }
                );
            } else {
                $zone->delete();
            }
        }

        return $inputZones;
    }

    public function delete(Site $site): JsonResponse
    {
        DB::beginTransaction();

        try {
            $site->delete();
            $site->zones()->delete();
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }

        DB::commit();

        return self::json(['message' => 'Successfully deleted']);
    }

    public function list(): JsonResponse
    {
        $siteCollection = Site::get();
        $sites = [];
        foreach ($siteCollection as $site) {
            $sites[] = $this->processClassificationInFiltering($site);
        }

        return self::json($sites);
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

    public function changeStatus(Site $site, Request $request): JsonResponse
    {
        if (!$request->has('site.status')) {
            throw new InvalidArgumentException('No status provided');
        }

        $status = (int)$request->input('site.status');

        $site->changeStatus($status);
        $site->save();

        return self::json([
            'site' => [
                'status' => $site->status,
            ],
        ]);
    }

    public function readSitesSizes(?int $siteId = null): JsonResponse
    {
        $response = new SizesResponse($siteId);

        return self::json($response);
    }
}
