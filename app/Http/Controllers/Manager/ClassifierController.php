<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

declare(strict_types=1);

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\Request\Classifier\NetworkBannerFilter;
use Adshares\Adserver\Http\Response\Classifier\ClassifierResponse;
use Adshares\Adserver\Models\Classification;
use Adshares\Adserver\Models\NetworkBanner;
use Adshares\Adserver\Models\Site;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ClassifierController extends Controller
{
    public function fetch(Request $request, ?int $siteId = null): JsonResponse
    {
        $limit = (int)$request->get('limit', 20);
        $offset = (int)$request->get('offset', 0);
        $userId = Auth::user()->id;

        $networkBannerFilter = new NetworkBannerFilter($request, $userId, $siteId);
        $banners = NetworkBanner::fetchByFilter($networkBannerFilter, Site::fetchAll());

        $paginated = $banners->slice($offset, $limit);

        $bannerIds = $this->getIdsFromBanners($paginated);
        $classifications = Classification::fetchByBannerIds($bannerIds);

        $items = (new ClassifierResponse($paginated, $classifications, $siteId))->toArray();
        $count = count($items);

        $response = [];
        $response['limit'] = $limit;
        $response['offset'] = $offset;
        $response['items_count'] = $count;
        $response['items_count_all'] = count($banners);
        $response['items'] = $items;

        return self::json($response);
    }

    private function getIdsFromBanners(Collection $banners): array
    {
        return $banners->map(
            function (NetworkBanner $banner) {
                return $banner->id;
            }
        )->toArray();
    }

    public function add(Request $request, int $siteId = null): JsonResponse
    {
        $input = $request->request->all();
        $classification = $input['classification'];
        $userId = (int)Auth::user()->id;

        if (!isset($input['classification'], $classification['banner_id'], $classification['status'])) {
            throw new BadRequestHttpException('Wrong input parameters.');
        }

        $bannerId = (int)$classification['banner_id'];
        $status = (bool)$classification['status'];
        $banner = NetworkBanner::find($bannerId);

        if (!$banner) {
            throw new NotFoundHttpException(sprintf('Banner %s does not exist.', $bannerId));
        }

        try {
            Classification::classify($userId, $bannerId, $status, $siteId);
        } catch (QueryException $exception) {
            throw new AccessDeniedHttpException('Operation cannot be proceed. Wrong permissions.');
        }

        return self::json([], Response::HTTP_NO_CONTENT);
    }
}
