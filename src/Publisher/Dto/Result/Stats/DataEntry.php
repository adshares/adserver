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

namespace Adshares\Publisher\Dto\Result\Stats;

class DataEntry
{
    /** @var Calculation */
    private $calculation;

    /** @var int */
    private $siteId;

    /** @var string */
    private $siteName;

    /** @var int|null */
    private $zoneId;

    /** @var string|null */
    private $zoneName;

    /** @var string|null */
    private $publisherId;

    public function __construct(
        Calculation $calculation,
        int $siteId,
        string $siteName,
        ?int $zoneId = null,
        ?string $zoneName = null,
        ?string $publisherId = null
    ) {
        $this->calculation = $calculation;
        $this->siteId = $siteId;
        $this->siteName = $siteName;
        $this->zoneId = $zoneId;
        $this->zoneName = $zoneName;
        $this->publisherId = $publisherId;
    }

    public function toArray(): array
    {
        $data = $this->calculation->toArray();
        $data['siteId'] = $this->siteId;
        $data['siteName'] = $this->siteName;

        if ($this->zoneId && $this->zoneName) {
            $data['zoneId'] = $this->zoneId;
            $data['zoneName'] = $this->zoneName;
        }

        if ($this->publisherId) {
            $data['publisherId'] = $this->publisherId;
        }

        return $data;
    }
}
