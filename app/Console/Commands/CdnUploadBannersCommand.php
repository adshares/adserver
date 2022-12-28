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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Repository\CampaignRepository;
use Adshares\Adserver\Services\Cdn\CdnProviderFactory;
use Exception;
use Illuminate\Support\Facades\Log;

class CdnUploadBannersCommand extends BaseCommand
{
    public const COMMAND_SIGNATURE = 'ops:demand:cdn:upload';
    protected $signature = self::COMMAND_SIGNATURE . ' {provider?} {--campaignIds=} {--f|force}';
    protected $description = 'Upload banners to CDN';

    public function __construct(
        private readonly CampaignRepository $campaignRepository,
        Locker $locker,
    ) {
        parent::__construct($locker);
    }

    public function handle(): void
    {
        if (!$this->lock()) {
            $this->info('Command ' . self::COMMAND_SIGNATURE . ' already running');
            return;
        }

        $this->info('Start command ' . self::COMMAND_SIGNATURE);

        $cdn = CdnProviderFactory::getProvider($this->argument('provider'));
        if (null === $cdn) {
            $this->warn('There is no CDN provider');
            return;
        }

        /** @var Banner $banner */
        foreach ($this->getBannerIds() as $bannerId) {
            $banner = (new Banner())->find($bannerId);
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

        $this->info('Finish command ' . self::COMMAND_SIGNATURE);
    }

    private function getBannerIds(): array
    {
        if (null !== ($campaignIds = $this->option('campaignIds'))) {
            $campaignIds = explode(',', $campaignIds);
        }

        $campaigns = $campaignIds !== null ? $this->campaignRepository->fetchCampaignByIds($campaignIds)
            : $this->campaignRepository->fetchActiveCampaigns();

        $bannerIds = [];
        foreach ($campaigns as $campaign) {
            $builder = $campaign->banners();
            if (!$this->option('force')) {
                $builder->whereNull('cdn_url');
            }

            $collection = $builder->get()->pluck('id')->toArray();
            array_push($bannerIds, ...$collection);
        }

        return $bannerIds;
    }
}
