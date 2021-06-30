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

class CdnResetBannersCommand extends BaseCommand
{
    protected $signature = 'ops:demand:cdn:reset {--campaignIds=}';

    protected $description = 'Reset banners CDN URLs';

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
        $builder = Banner::whereNotNull('cdn_url');
        if (null !== ($campaignIds = $this->option('campaignIds'))) {
            $campaignIds = explode(',', $campaignIds);
            $builder->whereIn('campaign_id', $campaignIds);
        }
        $count = $builder->update(['cdn_url' => null]);
        $this->info(sprintf('Reset %d banners', $count));
        $this->info('Finish command ' . $this->signature);
    }
}
