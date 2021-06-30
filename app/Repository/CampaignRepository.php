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

namespace Adshares\Adserver\Repository;

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\Campaign;
use DateTime;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class CampaignRepository
{
    public function find()
    {
        return (new Campaign())->with('conversions')->get();
    }

    public function fetchActiveCampaigns(): Collection
    {
        $query = Campaign::where('campaigns.status', Campaign::STATUS_ACTIVE);

        $query->where(
            function ($q) {
                $q->where('campaigns.time_end', '>', new DateTime())
                    ->orWhere('campaigns.time_end', null);
            }
        );

        return $query->with('banners')->get();
    }

    public function fetchCampaignByIds(array $campaignIds): Collection
    {
        return Campaign::whereIn('id', $campaignIds)->with('banners')->get();
    }

    public function fetchCampaignById(int $campaignId): Campaign
    {
        return (new Campaign())->with('banners')->findOrFail($campaignId);
    }

    public function fetchCampaignByIdWithConversions(int $campaignId): Campaign
    {
        return (new Campaign())->with('conversions')->findOrFail($campaignId);
    }

    /**
     * @param Campaign $campaign
     * @param array $banners
     * @param array $conversions
     *
     * @throws \Exception
     */
    public function save(Campaign $campaign, array $banners = [], array $conversions = []): void
    {
        DB::beginTransaction();

        try {
            $campaign->save();

            if ($banners) {
                foreach ($banners as $banner) {
                    $campaign->banners()->save($banner);
                }
            }

            if ($conversions) {
                foreach ($conversions as $conversion) {
                    $campaign->conversions()->save($conversion);
                }
            }
        } catch (\Exception $ex) {
            DB::rollBack();
            throw $ex;
        }

        DB::commit();
    }

    public function delete(Campaign $campaign): void
    {
        DB::beginTransaction();

        try {
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

    public function update(
        Campaign $campaign,
        array $bannersToInsert = [],
        array $bannersToUpdate = [],
        array $bannersToDelete = [],
        array $conversionsToInsert = [],
        array $conversionsToUpdate = [],
        array $conversionUuidsToDelete = []
    ): void {
        DB::beginTransaction();

        try {
            $campaign->update();

            if ($bannersToInsert) {
                foreach ($bannersToInsert as $banner) {
                    $campaign->banners()->save($banner);
                }
            }

            if ($bannersToUpdate) {
                foreach ($bannersToUpdate as $banner) {
                    $banner->update();
                }
            }

            if ($bannersToDelete) {
                foreach ($bannersToDelete as $banner) {
                    $banner->classifications()->delete();
                    $banner->delete();
                }
            }

            if ($conversionsToInsert) {
                foreach ($conversionsToInsert as $conversion) {
                    $campaign->conversions()->save($conversion);
                }
            }

            if ($conversionsToUpdate) {
                foreach ($conversionsToUpdate as $conversion) {
                    $conversion->update();
                }
            }

            if ($conversionUuidsToDelete) {
                $conversionBinaryUuidsToDelete = array_map(
                    function ($uuid) {
                        return hex2bin($uuid);
                    },
                    $conversionUuidsToDelete
                );

                $campaign->conversions()->whereIn('uuid', $conversionBinaryUuidsToDelete)->delete();
            }
        } catch (\Exception $ex) {
            DB::rollBack();
            throw $ex;
        }

        DB::commit();
    }
}
