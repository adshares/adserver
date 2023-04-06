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
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\Store\FlockStore;

class SiteAdsTxtCheckCommand extends BaseCommand
{
    protected $signature = 'ops:supply:site-ads-txt:check';

    protected $description = 'Checks if sites have valid ads.txt files';

    public function handle(AdsTxtCrawler $adsTxtCrawler): int
    {
        $lock = new Lock(new Key($this->name), new FlockStore(), null, false);
        if (!$lock->acquire()) {
            $this->info(sprintf('Command %s already running', $this->name));
            return self::FAILURE;
        }
        $this->info(sprintf('Start command %s', $this->name));

        $limit = 20;
        $offset = 0;
        while (true) {
            $sites = Site::fetchSitesWhichNeedAdsTxtConfirmation($limit, $offset);
            if ($sites->isEmpty()) {
                break;
            }
            $offset += $limit;
            $results = $adsTxtCrawler->checkSites($sites);
            $sitesByIds = $sites->keyBy('id');
            foreach ($results as $siteId => $result) {
                /** @var Site $site */
                $site = $sitesByIds->get($siteId);
                if ($result) {
                    $site->ads_txt_confirmed_at = new DateTimeImmutable();
                    $site->approvalProcedure();
                } else {
                    $site->ads_txt_confirmed_at = null;
                    if (Site::STATUS_ACTIVE === $site->status) {
                        $site->status = Site::STATUS_PENDING_APPROVAL;
                    }
                }
                $site->saveOrFail();
            }
        }

        $this->info(sprintf('Finish command %s', $this->name));
        $lock->release();
        return self::SUCCESS;
    }
}
