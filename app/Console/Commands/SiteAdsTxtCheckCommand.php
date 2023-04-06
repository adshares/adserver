<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Services\Common\AdsTxtCrawler;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\Store\FlockStore;

class SiteAdsTxtCheckCommand extends Command
{
    private const SITE_PACK_SIZE = 20;

    protected $signature = 'ops:supply:site-ads-txt:check';

    protected $description = 'Checks if sites have valid ads.txt files';

    public function __construct(private readonly AdsTxtCrawler $adsTxtCrawler)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (!config('app.ads_txt_crawler_enabled')) {
            $this->info('ads.txt crawler is disabled');
            return self::SUCCESS;
        }

        $lock = new Lock(new Key($this->name), new FlockStore(), null, false);
        if (!$lock->acquire()) {
            $this->info(sprintf('Command %s already running', $this->name));
            return self::FAILURE;
        }
        $this->info(sprintf('Start command %s', $this->name));

        $lastId = 0;
        while (true) {
            $sites = Site::fetchSitesWhichNeedAdsTxtConfirmation($lastId, self::SITE_PACK_SIZE);
            if ($sites->isEmpty()) {
                break;
            }
            $lastId = $sites->last()->id;
            $this->checkAdsTxtForSites($sites);
        }

        $lastId = 0;
        while (true) {
            $sites = Site::fetchSitesWhichNeedAdsTxtRefresh($lastId, self::SITE_PACK_SIZE);
            if ($sites->isEmpty()) {
                break;
            }
            $lastId = $sites->last()->id;
            $this->checkAdsTxtForSites($sites);
        }

        $this->info(sprintf('Finish command %s', $this->name));
        $lock->release();
        return self::SUCCESS;
    }

    private function checkAdsTxtForSites(Collection $sites): void
    {
        $now = new DateTimeImmutable();
        $results = $this->adsTxtCrawler->checkSites($sites);
        $sitesByIds = $sites->keyBy('id');
        foreach ($results as $siteId => $result) {
            /** @var Site $site */
            $site = $sitesByIds->get($siteId);
            $site->ads_txt_check_at = $now;
            if ($result) {
                $site->ads_txt_confirmed_at = $now;
                $site->ads_txt_fails = 0;
                $site->approvalProcedure(false);
            } else {
                $site->ads_txt_confirmed_at = null;
                $site->ads_txt_fails = $site->ads_txt_fails + 1;
                $site->status = Site::STATUS_PENDING_APPROVAL;
            }
            $site->saveOrFail();
        }
    }
}
