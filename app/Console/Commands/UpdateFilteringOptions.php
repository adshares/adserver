<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

use Adshares\Adserver\Console\LineFormatterTrait;
use Adshares\Supply\Application\Service\FilteringOptionsImporter;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\LockableTrait;

class UpdateFilteringOptions extends Command
{
    use LineFormatterTrait;
    use LockableTrait;

    protected $signature = 'ops:filtering-options:update';

    public function handle(FilteringOptionsImporter $service): void
    {
        if (!$this->lock()) {
            $this->info('[UpdateFilteringOptions] Command '.$this->signature.' already running.');

            return;
        }

        $this->info('Start command '.$this->signature);

        $service->import();

        $this->info('Finish command '.$this->signature);
    }
}
