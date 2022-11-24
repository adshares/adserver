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

namespace Adshares\Adserver\Repository;

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Common\Exception\RuntimeException;
use DateTime;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Log;
use Throwable;

class CampaignRepository
{
    public function find()
    {
        return (new Campaign())->with('conversions')->get();
    }

    /**
     * @return Collection|Campaign[]
     */
    public function findByUserId(int $userId): Collection
    {
        return (new Campaign())->where('user_id', $userId)->get();
    }

    /**
     * @return Collection|Campaign[]
     */
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

    /**
     * @param array $campaignIds
     * @return Collection|Campaign[]
     */
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
     * @return Campaign
     *
     * @throws RuntimeException
     */
    public function save(Campaign $campaign, array $banners = [], array $conversions = []): Campaign
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
            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error(sprintf('Campaign save failed (%s)', $throwable->getMessage()));
            throw new RuntimeException('Campaign save failed');
        }

        return $campaign;
    }

    public function delete(Campaign $campaign): void
    {
        DB::beginTransaction();
        try {
            if (Campaign::STATUS_INACTIVE !== $campaign->status) {
                $campaign->status = Campaign::STATUS_INACTIVE;
                $this->save($campaign);
            }
            $campaign->conversions()->delete();
            $campaign->delete();
            foreach ($campaign->banners as $banner) {
                $banner->classifications()->delete();
            }
            $campaign->banners()->delete();
            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error(sprintf('Campaign deletion failed (%s)', $throwable->getMessage()));
            throw new RuntimeException('Campaign deletion failed');
        }
    }

    public function update(
        Campaign $campaign,
        array $bannersToInsert = [],
        array $bannersToUpdate = [],
        array $bannersToDelete = [],
        array $conversionsToInsert = [],
        array $conversionsToUpdate = [],
        array $conversionUuidsToDelete = [],
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
            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error(sprintf('Campaign update failed (%s)', $throwable->getMessage()));
            throw new RuntimeException('Campaign update failed');
        }
    }

    public function fetchCampaignByIdSimple(int $id): Campaign
    {
        return Campaign::findOrFail($id);
    }

    public function fetchBanner(Campaign $campaign, int $bannerId): Banner
    {
        return $campaign->banners()->findOrFail($bannerId);
    }

    public function fetchBanners(Campaign $campaign): Collection
    {
        return $campaign->banners()->get();
    }

    public function fetchCampaigns(?int $perPage = null): CursorPaginator
    {
        return Campaign::query()->orderBy('id')
            ->tokenPaginate($perPage)
            ->withQueryString();
    }
}
