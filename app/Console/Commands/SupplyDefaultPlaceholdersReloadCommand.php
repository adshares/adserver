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

namespace Adshares\Adserver\Console\Commands;

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Services\Supply\DefaultBannerPlaceholderGenerator;

class SupplyDefaultPlaceholdersReloadCommand extends BaseCommand
{
    protected $signature = 'ops:supply:default-placeholders:reload';
    protected $description = 'Recreates default supply banner placeholders';

    public function __construct(private readonly DefaultBannerPlaceholderGenerator $generator, Locker $locker)
    {
        parent::__construct($locker);
    }

    public function handle(): int
    {
        if (!$this->lock()) {
            $this->info('Command ' . $this->getName() . ' already running');
            return self::FAILURE;
        }
        $this->info('Start command ' . $this->getName());
        $this->generator->generate(true);
        $this->info('End command ' . $this->getName());
        return self::SUCCESS;
    }
}
