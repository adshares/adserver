<?php

namespace Adshares\Adserver\Repository;

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\Campaign;

class CampaignRepository
{
    public function find()
    {
        return (new Campaign())->get();
    }

    public function fetchCampaignById(int $campaignId): Campaign
    {
        return (new Campaign())->findOrFail($campaignId);
    }

    /**
     * @param Campaign $campaign
     * @param array $banners
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

}
