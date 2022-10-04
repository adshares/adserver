<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Supply\Application\Dto\Info;
use Adshares\Supply\Domain\ValueObject\HostStatus;
use Adshares\Supply\Domain\ValueObject\Status;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int id
 * @property string address
 * @property string host
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property Carbon|null deleted_at
 * @property Carbon last_broadcast
 * @property Carbon|null last_synchronization
 * @property int failed_connection
 * @property Info info
 * @property string info_url
 * @property HostStatus status
 * @property string|null error
 * @mixin Builder
 */
class NetworkHost extends Model
{
    use AutomateMutators;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var array
     */
    protected $fillable = [
        'address',
        'host',
        'last_broadcast',
        'failed_connection',
        'info',
    ];

    protected $casts = [
        'info' => 'json',
        'status' => HostStatus::class,
    ];

    protected $dates = [
        'last_broadcast',
        'last_synchronization',
    ];

    public static function fetchByAddress(string $address): ?self
    {
        return self::where('address', $address)->first();
    }

    public static function fetchByHost(string $host): ?self
    {
        return self::where('host', $host)->first();
    }

    public static function fetchBroadcastedAfter(DateTimeInterface $date): Collection
    {
        return self::where('last_broadcast', '>', $date)->get();
    }

    public static function deleteBroadcastedBefore(DateTimeInterface $date): int
    {
        $hosts = self::where('last_broadcast', '<', $date);
        $counter = $hosts->count();
        $hosts->delete();
        return $counter;
    }

    public static function registerHost(
        string $address,
        string $infoUrl,
        Info $info,
        ?DateTimeInterface $lastBroadcast = null,
        ?string $error = null,
    ): NetworkHost {
        $networkHost = self::withTrashed()->where('address', $address)->first();

        if (empty($networkHost)) {
            $networkHost = new self();
            $networkHost->address = $address;
        }

        $networkHost->deleted_at = null;
        $networkHost->host = $info->getServerUrl();
        $networkHost->last_broadcast = $lastBroadcast ?? new DateTimeImmutable();
        $networkHost->failed_connection = 0;
        $networkHost->info = $info;
        $networkHost->info_url = $infoUrl;
        $networkHost->status = null === $error ? HostStatus::Initialization : HostStatus::Failure;
        $networkHost->error = $error;
        $networkHost->save();

        return $networkHost;
    }

    public static function fetchHosts(array $whitelist = []): Collection
    {
        $query = self::whereIn(
            'status',
            [HostStatus::Initialization, HostStatus::Operational],
        );
        if (!empty($whitelist)) {
            $query->whereIn('address', $whitelist);
        }
        return $query->get();
    }

    public function connectionSuccessful(): void
    {
        $this->last_synchronization = new Carbon();
        $this->failed_connection = 0;
        $this->status = HostStatus::Operational;
        $this->update();
    }

    public function connectionFailed(): void
    {
        ++$this->failed_connection;
        if ($this->failed_connection >= config('app.inventory_failed_connection_limit')) {
            $this->status = HostStatus::Unreachable;
        }
        $this->update();
    }

    public function resetConnectionErrorCounter(): void
    {
        $this->failed_connection = 0;
        $this->status = HostStatus::Initialization;
        $this->update();
    }

    public function isInventoryToBeRemoved(): bool
    {
        return HostStatus::Unreachable === $this->status;
    }

    public function getInfoAttribute(): Info
    {
        $info = json_decode($this->attributes['info'], true);

        return Info::fromArray($info);
    }

    public function setInfoAttribute(Info $info): void
    {
        $this->attributes['info'] = json_encode($info->toArray());
    }

    public static function findNonExistentHostsAddresses(array $whitelist = []): array
    {
        $self = new self();

        $query = $self
            ->select(['network_campaigns.source_address as address'])
            ->rightJoin('network_campaigns', function ($join) {
                $join->on('network_hosts.address', '=', 'network_campaigns.source_address')
                    ->whereNull('network_hosts.deleted_at');
            })
            ->where(
                function ($query) use ($whitelist) {
                    $query->where('network_hosts.address', null);
                    if (!empty($whitelist)) {
                        $query->orWhereNotIn('network_campaigns.source_address', $whitelist);
                    }
                }
            )
            ->where('network_campaigns.status', '=', Status::STATUS_ACTIVE);

        return $query->get()->pluck('address')->toArray();
    }
}
