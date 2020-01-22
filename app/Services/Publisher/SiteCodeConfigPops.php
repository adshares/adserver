<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types = 1);

namespace Adshares\Adserver\Services\Publisher;

class SiteCodeConfigPops
{
    /** @var int */
    private $count;

    /** @var int */
    private $interval;

    /** @var int */
    private $burst;

    public function __construct(int $count = 1, int $interval = 1, int $burst = 1)
    {
        $this->count = $count;
        $this->interval = $interval;
        $this->burst = $burst;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getInterval(): int
    {
        return $this->interval;
    }

    public function getBurst(): int
    {
        return $this->burst;
    }
}
