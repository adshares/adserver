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
use Adshares\Classify\Domain\Model\Classification;
use Adshares\Common\Exception\InvalidArgumentException;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\JsonResponse;

class SitesController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        $this->validateRequestObject($request, 'site', Site::$rules);
        $input = $request->input('site');

        DB::beginTransaction();

        try {
            $site = Site::create($input);
            $site->user_id = Auth::user()->id;
            $site->save();
            $this->addClassificationToSiteFiltering($site);

            $site->zones()->createMany($request->input('site.ad_units'));
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }

        DB::commit();

        return self::json([], Response::HTTP_CREATED)->header('Location', route('app.sites.read', ['site' => $site]));
    }

    private function addClassificationToSiteFiltering(Site $site): void
    {
        $namespace = (string)config('app.classify_namespace');

        $siteRequires = $site->site_requires;
        $siteExcludes = $site->site_excludes;

        unset($siteRequires[$namespace]);
        unset($siteExcludes[$namespace]);

        $publisherId = $site->user_id;
        $siteId = $site->id;

        if ($site->require_classified) {
            list($requireKeywords, $excludeKeywords) =
                $this->getClassificationForPositiveCase($namespace, $publisherId, $siteId);

            /** @var Classification $requireKeyword */
            foreach ($requireKeywords as $requireKeyword) {
                $siteRequires[$requireKeyword->getNamespace()][] = $requireKeyword->keyword();
            }
            /** @var Classification $excludeKeyword */
            foreach ($excludeKeywords as $excludeKeyword) {
                $siteExcludes[$excludeKeyword->getNamespace()][] = $excludeKeyword->keyword();
            }
        }

        if ($site->exclude_unclassified) {
            $excludeKeywords = $this->getClassificationNotNegativeCase($namespace, $publisherId, $siteId);

            /** @var Classification $excludeKeyword */
            foreach ($excludeKeywords as $excludeKeyword) {
                $namespace = $excludeKeyword->getNamespace();
                $keyword = $excludeKeyword->keyword();

                if (!in_array($keyword, $siteExcludes[$namespace], true)) {
                    $siteExcludes[$namespace][] = $keyword;
                }
            }
        }

        $site->site_excludes = $siteExcludes;
        $site->site_requires = $siteRequires;
        $site->save();
    }

    private function getClassificationForPositiveCase(string $namespace, int $publisherId, int $siteId): array
    {
        $requireKeywords = [
            new Classification($namespace, $publisherId, true),
            new Classification($namespace, $publisherId, true, $siteId),
        ];
        $excludeKeywords = [
            new Classification($namespace, $publisherId, false, $siteId),
        ];

        return [$requireKeywords, $excludeKeywords];
    }

    private function getClassificationNotNegativeCase(string $namespace, int $publisherId, int $siteId): array
    {
        return [
            new Classification($namespace, $publisherId, false),
            new Classification($namespace, $publisherId, false, $siteId),
        ];
    }

    public function read(Site $site): JsonResponse
    {
        return self::json($this->processInternalClassificationInFiltering($site));
    }

    private function processInternalClassificationInFiltering(Site $site): array
    {
        $namespace = (string)config('app.classify_namespace');

        $siteArray = $site->toArray();

        $filtering = $siteArray['filtering'];

        if ($filtering['requires'][$namespace] ?? false) {
            unset($filtering['requires'][$namespace]);

            if (!$filtering['requires']) {
                $filtering['requires'] = null;
            }
        }
        if ($filtering['excludes'][$namespace] ?? false) {
            unset($filtering['excludes'][$namespace]);

            if (!$filtering['excludes']) {
                $filtering['excludes'] = null;
            }
        }

        $siteArray['filtering'] = $filtering;

        return $siteArray;
    }

    public function update(Request $request, Site $site): JsonResponse
    {
        $input = $request->input('site');
        $this->validateRequestObject($request, 'site', array_intersect_key(Site::$rules, $input));

        DB::beginTransaction();

        try {
            $site->fill($input);
            $site->push();
            $this->addClassificationToSiteFiltering($site);

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
            $sites[] = $this->processInternalClassificationInFiltering($site);
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
