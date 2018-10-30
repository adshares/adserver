<?php

namespace Adshares\Adserver\Repository;

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\Campaign;

class CampaignRepository
{
    /**
     * @var Campaign
     */
    private $model;

    public function __construct(Campaign $model)
    {
        $this->model = $model;
    }

    public function find()
    {
        return $this->model->whereNull('deleted_at')->get();
    }

    public function fetchCampaignById(int $campaignId): Campaign
    {
        return $this->model->whereNull('deleted_at')->findOrFail($campaignId);
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
