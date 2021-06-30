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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Services\Cdn\CdnProviderFactory;
use Exception;
use Illuminate\Support\Facades\Log;

class CdnUploadBannersCommand extends BaseCommand
{
    protected $signature = 'ops:demand:cdn:upload {provider?} {--campaignIds=} {--f|force}';

    protected $description = 'Upload banners to CDN';

    public function __construct(
        Locker $locker
    ) {
        parent::__construct($locker);
    }

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('Command ' . $this->signature . ' already running');

            return;
        }

        $this->info('Start command ' . $this->signature);

        $cdn = CdnProviderFactory::getProvider($this->argument('provider'));
        if (null === $cdn) {
            $this->warn('There is no CDN provider');
            return;
        }

        /** @var Banner $banner */
        foreach ($this->getBanners() as $banner) {
            $this->getOutput()->write(sprintf('Uploading banner %s ', $banner->uuid));
            try {
                $url = $cdn->uploadBanner($banner);
                $banner->cdn_url = $url;
                $banner->saveOrFail();
                $this->info(sprintf('OK: %s', $url));
            } catch (Exception $exception) {
                Log::error($exception->getMessage());
                $this->error(sprintf('ERROR: %s', $exception->getMessage()));
            }
        }

        $this->info('Finish command ' . $this->signature);
    }

    private function getBanners(): array
    {
        if (null !== ($campaignIds = $this->option('campaignIds'))) {
            $campaignIds = explode(',', $campaignIds);
        }

        $campaignRepository = new CampaignRepository();
        $campaigns = $campaignIds !== null ? $campaignRepository->fetchCampaignByIds($campaignIds)
            : $campaignRepository->fetchActiveCampaigns();

        $banners = [];
        /** @var Campaign $campaign */
        foreach ($campaigns as $campaign) {
            $builder = $campaign->banners();
            if (!$this->option('force')) {
                $builder->whereNull('cdn_url');
            }
            foreach ($builder->get() as $banner) {
                $banners[] = $banner;
            }
        }

        return $banners;
    }
}
