<?php

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
            'campaignCount' => $statistics['campaigns'] ?? 0,
            'siteCount' => $statistics['sites'] ?? 0,
            'connectionErrorCount' => $this->failed_connection,
            'infoJson' => $info->toArray(),
            'error' => $this->error,
        ];
    }
}
