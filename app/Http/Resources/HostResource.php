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

namespace Adshares\Adserver\Http\Resources;

use Adshares\Adserver\Models\NetworkHost;
use DateTimeInterface;
use Illuminate\Http\Resources\Json\JsonResource;

class HostResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var NetworkHost $this */
        $info = $this->info;
        $statistics = $info->getStatistics()?->toArray() ?? [];
        return [
            'id' => $this->id,
            'status' => $this->status,
            'name' => $info->getName(),
            'url' => $this->host,
            'walletAddress' => $this->address,
            'lastBroadcast' => $this->last_broadcast->format(DateTimeInterface::ATOM),
            'lastSynchronization' => $this->last_synchronization?->format(DateTimeInterface::ATOM),
            'lastSynchronizationAttempt' => $this->last_synchronization_attempt?->format(DateTimeInterface::ATOM),
            'campaignCount' => $statistics['campaigns'] ?? 0,
            'siteCount' => $statistics['sites'] ?? 0,
            'connectionErrorCount' => $this->failed_connection,
            'infoJson' => $info->toArray(),
            'error' => $this->error,
        ];
    }
}
