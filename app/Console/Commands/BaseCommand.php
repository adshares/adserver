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

use Adshares\Adserver\Console\LineFormatterTrait;
use Adshares\Adserver\Console\Locker;
use Illuminate\Console\Command;

class BaseCommand extends Command
{
    use LineFormatterTrait;

    /** @var Locker */
    private $locker;

    protected $signature = 'base:command';

    protected $description = 'This method should be used for inheritance only';

    public function __construct(Locker $locker)
    {
        $this->locker = $locker;

        parent::__construct();
    }

    protected function lock($name = null, $blocking = false): bool
    {
        $lockId = $name ?: config('app.adserver_id') . $this->getName();

        return $this->locker->lock($lockId, $blocking);
    }

    protected function release(): void
    {
        $this->locker->release();
    }
}
