<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Repository;

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\ConversionDefinition;
use DateTime;

class CampaignRepository
{
    public function find()
    {
        return (new Campaign())->get();
    }

    public function fetchActiveCampaigns()
    {
        $query = Campaign::where('status', Campaign::STATUS_ACTIVE);

        $query->where(
            function ($q) {
                $q->where('time_end', '>', new DateTime())
                    ->orWhere('time_end', null);
            }
        );

        return $query->get();
    }

    public function fetchCampaignById(int $campaignId): Campaign
    {
        return (new Campaign())->findOrFail($campaignId);
    }

    public function fetchCampaignByIdWithConversions(int $campaignId): Campaign
    {
        return (new Campaign())->with('conversions')->findOrFail($campaignId);
    }

    /**
     * @param Campaign $campaign
     * @param array $banners
     *
     * @throws \Exception
     */
    public function save(Campaign $campaign, array $banners = []): void
    {
        DB::beginTransaction();

        try {
            $campaign->save();

            if ($banners) {
                foreach ($banners as $banner) {
                    $campaign->banners()->save($banner);
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
        array $conversions = []
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
                    $banner->delete();
                }
            }

            if ($conversions) {
                $existedConversions = $this->findConversionsWhichMustStay($conversions);
                ConversionDefinition::removeWithoutGivenIds($existedConversions);

                foreach ($conversions as $conversionInput) {
                    if (isset($conversionInput['id']) && ConversionDefinition::find($conversionInput['id'])) {
                        continue;
                    }

                    unset($conversionInput['id']);
                    $conversion = new ConversionDefinition();
                    $conversion->fill($conversionInput);

                    $campaign->conversions()->save($conversion);
                }
            }
        } catch (\Exception $ex) {
            DB::rollBack();
            throw $ex;
        }

        DB::commit();
    }

    private function findConversionsWhichMustStay(array $conversions): array
    {
        $ids = [];
        foreach ($conversions as $conversion) {
            if (isset($conversion['id'])) {
                $ids[] = $conversion['id'];
            }
        }

        return $ids;
    }
}
