<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types = 1);

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Dto\Response\Classifier\ClassifierResponse;
use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Models\Classification;
use Adshares\Adserver\Models\NetworkBanner;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Request;

class ClassifierController extends Controller
{
    public function fetch(Request $request, ?int $siteId = null)
    {
        $limit = (int)$request->get('limit', 20);
        $offset = (int)$request->get('offset', 0);
        $banners = NetworkBanner::fetch($limit, $offset);
        $bannerIds = $this->getIdsFromBanners($banners);

        $classifications = Classification::fetchByBannerIds($bannerIds);


        $response = new ClassifierResponse($banners, $classifications, $siteId);

        return self::json($response);

    }

    private function getIdsFromBanners(Collection $banners): array
    {
        return $banners->map(function(NetworkBanner $banner) {
            return $banner->id;
        })->toArray();
    }
}
