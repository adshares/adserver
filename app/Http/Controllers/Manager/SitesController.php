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
use Adshares\Adserver\Http\Requests\GetSiteCode;
use Adshares\Adserver\Http\Response\Site\SizesResponse;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Services\Publisher\SiteCodeGenerator;
use Adshares\Adserver\Services\Supply\SiteClassificationUpdater;
use Adshares\Common\Application\Dto\DomainRank;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Supply\Domain\ValueObject\Size;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

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
        $inputZones = $request->input('site.ad_units');
        $this->validateInputZones($inputZones);

        DB::beginTransaction();

        try {
            $site = Site::create($input);
            $site->user_id = Auth::user()->id;
            $site->save();
            $this->siteClassificationUpdater->addClassificationToFiltering($site);

            if ($inputZones) {
                $site->zones()->createMany($this->processInputZones($site, $inputZones));
            }
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
        $siteArray = $site->toArray();
        $filtering = $siteArray['filtering'];

        $filtering['requires'] = array_filter(
            $filtering['requires'] ?: [],
            $this->filterOutHelperKeywords(),
            ARRAY_FILTER_USE_KEY
        );
        $filtering['excludes'] = array_filter(
            $filtering['excludes'] ?: [],
            $this->filterOutHelperKeywords(),
            ARRAY_FILTER_USE_KEY
        );

        $siteArray['filtering'] = $filtering;

        return $siteArray;
    }

    private function filterOutHelperKeywords(): Closure
    {
        return function ($key) {
            return SiteClassificationUpdater::INTERNAL_CLASSIFIER_NAMESPACE !== $key
                && false === strpos($key, SiteClassificationUpdater::KEYWORD_CLASSIFIED);
        };
    }

    public function update(Request $request, Site $site): JsonResponse
    {
        $input = $request->input('site');
        $this->validateRequestObject($request, 'site', array_intersect_key(Site::$rules, $input));
        $inputZones = $request->input('site.ad_units');
        $this->validateInputZones($inputZones);

        DB::beginTransaction();

        try {
            $site->fill($input);
            $site->push();
            $this->siteClassificationUpdater->addClassificationToFiltering($site);

            if ($inputZones) {
                $site->zones()->createMany($this->processInputZones($site, $inputZones));
            }
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }

        DB::commit();

        return self::json(['message' => 'Successfully edited']);
    }

    private function processInputZones(Site $site, array $inputZones): array
    {
        $presentUniqueSizes = [];
        $keysToRemove = [];

        foreach ($inputZones as $key => &$inputZone) {
            $size = $inputZone['size'];
            $type = Size::SIZE_INFOS[$size]['type'];
            $inputZone['type'] = $type;

            if (Size::TYPE_POP !== $type) {
                continue;
            }

            if (isset($presentUniqueSizes[$size])) {
                $keysToRemove[] = $key;
            } else {
                $presentUniqueSizes[$size] = $key;
            }
        }
        unset($inputZone);

        foreach ($keysToRemove as $key) {
            unset($inputZones[$key]);
        }

        /** @var Zone $zone */
        foreach ($site->zones()->withTrashed()->get() as $zone) {
            if (Size::TYPE_POP === $zone->type) {
                $size = $zone->size;

                if (isset($presentUniqueSizes[$size])) {
                    if ($zone->trashed()) {
                        $zone->restore();
                    }

                    $key = $presentUniqueSizes[$size];
                    $zone->update($inputZones[$key]);
                    unset($inputZones[$key]);
                } else {
                    if (!$zone->trashed()) {
                        $zone->delete();
                    }
                }

                continue;
            }

            if ($zone->trashed()) {
                continue;
            }

            $zoneFromInput = null;
            foreach ($inputZones as $key => $inputZone) {
                if (($inputZone['id'] ?? null) === $zone->id) {
                    $zoneFromInput = $inputZone;
                    unset($inputZones[$key]);

                    break;
                }
            }

            if (null !== $zoneFromInput) {
                $zone->update($zoneFromInput);
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

    public function readSiteRank(Site $site): JsonResponse
    {
        return self::json(new DomainRank($site->rank, $site->info));
    }

    /**
     * @deprecated This function is deprecated and will be removed in the future.
     * Use sitesCodes instead.
     * @see sitesCodes replacement for this function
     *
     * @param Site $site
     * @param GetSiteCode $request
     *
     * @return JsonResponse
     */
    public function sitesCode(Site $site, GetSiteCode $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isEmailConfirmed) {
            return self::json(['code' => 'Confirm e-mail to get code']);
        }

        return self::json(['code' => SiteCodeGenerator::generateAsSingleString($site, $request->toConfig())]);
    }

    public function sitesCodes(Site $site, GetSiteCode $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isEmailConfirmed) {
            return self::json(['message' => 'Confirm e-mail to get code'], JsonResponse::HTTP_FORBIDDEN);
        }

        return self::json(['codes' => SiteCodeGenerator::generate($site, $request->toConfig())]);
    }

    private function validateInputZones($inputZones): void
    {
        if (null === $inputZones) {
            return;
        }

        if (!is_array($inputZones)) {
            throw new UnprocessableEntityHttpException('Invalid ad units type.');
        }

        foreach ($inputZones as $inputZone) {
            if (!isset($inputZone['name']) || !is_string($inputZone['name'])) {
                throw new UnprocessableEntityHttpException('Invalid name.');
            }
            if (!isset($inputZone['size']) || !is_string($inputZone['size']) || !Size::isValid($inputZone['size'])) {
                throw new UnprocessableEntityHttpException('Invalid size.');
            }
        }
    }
}
