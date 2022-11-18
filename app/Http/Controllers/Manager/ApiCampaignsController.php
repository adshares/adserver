<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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
use Adshares\Adserver\Http\Requests\Common\LimitValidator;
use Adshares\Adserver\Http\Resources\CampaignCollection;
use Adshares\Adserver\Http\Resources\CampaignResource;
use Adshares\Adserver\Repository\Advertiser\CampaignRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiCampaignsController extends Controller
{
    public function fetchCampaignById(int $id, CampaignRepository $campaignRepository): JsonResource
    {
        return new CampaignResource($campaignRepository->fetchCampaignById($id));
    }

    public function fetchCampaigns(Request $request, CampaignRepository $campaignRepository): JsonResource
    {
        $limit = $request->query('limit', 10);
        LimitValidator::validate($limit);
        return new CampaignCollection($campaignRepository->fetchCampaigns($limit));
    }
}
