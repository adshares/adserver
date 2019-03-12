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
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\Zone;
use Adshares\Classify\Domain\Model\Classification;
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
        $siteRequires = $site->site_requires;
        $siteExcludes = $site->site_excludes;

        unset($siteRequires['classification']);
        unset($siteExcludes['classification']);

        $publisherId = $site->user_id;
        $siteId = $site->id;

        if ($site->require_classified) {
            list($requireKeywords, $excludeKeywords) =
                $this->getKeywordsForPositiveClassification($publisherId, $siteId);

            $siteRequires['classification'] = $requireKeywords;
            $siteExcludes['classification'] = $excludeKeywords;
        }
        if ($site->exclude_unclassified) {
            $excludeKeywords = $this->getKeywordsForNotNegativeClassification($publisherId, $siteId);

            if (empty($siteExcludes['classification'])) {
                $siteExcludes['classification'] = [];
            }

            foreach ($excludeKeywords as $excludeKeyword) {
                if (!in_array($excludeKeyword, $siteExcludes['classification'], true)) {
                    $siteExcludes['classification'][] = $excludeKeyword;
                }
            }
        }

        $site->site_excludes = $siteExcludes;
        $site->site_requires = $siteRequires;
        $site->save();
    }

    private function getKeywordsForPositiveClassification(int $publisherId, $siteId): array
    {
        $requireKeywords = [
            $this->createClassificationKeyword($publisherId, true),
            $this->createClassificationKeyword($publisherId, true, $siteId),
        ];
        $excludeKeywords = [
            $this->createClassificationKeyword($publisherId, false, $siteId),
        ];

        return [$requireKeywords, $excludeKeywords];
    }

    private function createClassificationKeyword(int $publisherId, bool $status, ?int $siteId = null): string
    {
        $classifyNamespace = (string)config('app.classify_namespace');

        return Classification::createUnsigned($classifyNamespace, $publisherId, $status, $siteId)->keyword();
    }

    private function getKeywordsForNotNegativeClassification(int $publisherId, $siteId): array
    {
        $excludeKeywords = [
            $this->createClassificationKeyword($publisherId, false),
            $this->createClassificationKeyword($publisherId, false, $siteId),
        ];

        return $excludeKeywords;
    }

    public function read(Site $site): JsonResponse
    {
        return self::json($this->processClassificationInFiltering($site));
    }

    private function processClassificationInFiltering(Site $site): array
    {
        $siteArray = $site->toArray();

        $filtering = $siteArray['filtering'];

        if ($filtering['requires']['classification'] ?? false) {
            unset($filtering['requires']['classification']);

            if (!$filtering['requires']) {
                $filtering['requires'] = null;
            }
        }
        if ($filtering['excludes']['classification'] ?? false) {
            unset($filtering['excludes']['classification']);

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
    
    public function readSitesZonesSizes(?int $siteId = null): JsonResponse
    {
        $zones = $this->getZones($siteId);
        
        $sizes = $zones->map(function(Zone $zone) {
            return $zone->getSizeAsString();
        })->unique()->values();

        return self::json(['sizes' => $sizes]);
    }

    private function getZones(?int $siteId): Collection
    {
        if (null === $siteId) {
            $sites = Site::get();
            if (!$sites) {
                return new Collection();
            }

            $zones = $sites->map(function (Site $site) {
                return $site->zones;
            })->flatten();
        } else {
            $site = Site::fetchById($siteId);
            if (!$site) {
                return new Collection();
            }

            $zones = $site->zones;
        }

        return $zones;
    }
}
