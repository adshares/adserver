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

namespace Adshares\Publisher\Dto;

class StatsEntry
{
    /** @var StatsEntryValues */
    private $statsEntryValues;

    /** @var string */
    private $siteId;

    /** @var string|null */
    private $zoneId;

    public function __construct(
        StatsEntryValues $statsEntryValues,
        string $siteId,
        ?string $zoneId = null
    ) {
        $this->statsEntryValues = $statsEntryValues;
        $this->siteId = $siteId;
        $this->zoneId = $zoneId;
    }

    public function toArray(): array
    {
        $data = $this->statsEntryValues->toArray();
        $data['siteId'] = $this->siteId;

        if ($this->zoneId) {
            $data['zoneId'] = $this->zoneId;
        }

        return $data;
    }
}
