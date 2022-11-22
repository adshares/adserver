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

declare(strict_types=1);

namespace Adshares\Adserver\Repository\Advertiser;

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\Campaign;
use Illuminate\Pagination\CursorPaginator;

class EloquentCampaignRepository implements CampaignRepository
{
    public function deleteCampaignById(int $id): void
    {
        $campaign = $this->fetchCampaignById($id);

        DB::beginTransaction();
        try {
            if (Campaign::STATUS_INACTIVE !== $campaign->status) {
                $campaign->status = Campaign::STATUS_INACTIVE;
                $campaign->save();
            }
            $campaign->conversions()->delete();
            $campaign->delete();
            foreach ($campaign->banners as $banner) {
                $banner->classifications()->delete();
            }
            $campaign->banners()->delete();
        } catch (\Exception $ex) {
            DB::rollBack();
            throw $ex;
        }
        DB::commit();
    }

    public function fetchCampaignById(int $id): Campaign
    {
        return Campaign::findOrFail($id);
    }

    public function fetchCampaigns(?int $perPage = null): CursorPaginator
    {
        return Campaign::query()->orderBy('id')
            ->tokenPaginate($perPage)
            ->withQueryString();
    }
}
