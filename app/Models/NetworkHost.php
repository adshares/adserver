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
 * @property Carbon|null last_synchronization_attempt
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

    private const DATETIME_FORMAT = 'Y-m-d H:i:s';
    private const MAXIMAL_PERIOD_FOR_SYNCHRONIZATION_RETRY_HOURS = 256;
    private const MESSAGE_WHILE_EXCLUDED = 'Server is not on a whitelist';

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
        'last_synchronization_attempt',
    ];

    public static function fetchByAddress(string $address): ?self
    {
        return (new self())->where('address', $address)->first();
    }

    public static function fetchByHost(string $host): ?self
    {
        return (new self())->where('host', $host)->first();
    }

    public static function failHostsBroadcastedBefore(DateTimeInterface $date): int
    {
        $hosts = (new self())->where('last_broadcast', '<', $date)->get();
        $counter = $hosts->count();
        /** @var NetworkHost $host */
        foreach ($hosts as $host) {
            $host->error = sprintf('No broadcast since %s', $host->last_broadcast->format(self::DATETIME_FORMAT));
            $host->status = HostStatus::Failure;
            $host->save();
        }
        return $counter;
    }

    public static function deleteBroadcastedBefore(DateTimeInterface $date): int
    {
        $hosts = (new self())->where('last_broadcast', '<', $date);
        $counter = $hosts->count();
        $hosts->delete();
        return $counter;
    }

    public static function registerHost(
        string $address,
        string $infoUrl,
        Info $info,
        DateTimeInterface $lastBroadcast,
        ?string $error = null,
    ): NetworkHost {
        $networkHost = self::withTrashed()->where('address', $address)->first();
        $newHost = null === $networkHost;
        if ($newHost) {
            $networkHost = new self();
            $networkHost->address = $address;
        }

        if ($newHost || HostStatus::Failure === $networkHost->status || $networkHost->deleted_at !== null) {
            $networkHost->deleted_at = null;
            $networkHost->failed_connection = 0;
            $networkHost->status = HostStatus::Initialization;
        }

        $networkHost->host = $info->getServerUrl();
        $networkHost->last_broadcast = $lastBroadcast;
        $networkHost->info = $info;
        $networkHost->info_url = $infoUrl;
        if (null !== $error) {
            $networkHost->status = HostStatus::Failure;
        }
        $networkHost->error = $error;
        $networkHost->save();

        return $networkHost;
    }

    public static function handleWhitelist(): void
    {
        /** @var NetworkHost $networkHost */
        foreach (self::all() as $networkHost) {
            $isWhitelisted = self::isWhitelisted($networkHost->address);
            if ($isWhitelisted && HostStatus::Excluded === $networkHost->status) {
                $networkHost->status = HostStatus::Initialization;
                $networkHost->error = null;
                $networkHost->update();
            } elseif (
                !$isWhitelisted
                && in_array($networkHost->status, [HostStatus::Initialization, HostStatus::Operational], true)
            ) {
                $networkHost->status = HostStatus::Excluded;
                $networkHost->error = self::MESSAGE_WHILE_EXCLUDED;
                $networkHost->update();
            }
        }
    }

    private static function isWhitelisted(string $address): bool
    {
        $whitelist = config('app.inventory_import_whitelist');

        return empty($whitelist) || in_array($address, $whitelist);
    }

    public static function fetchHosts(array $whitelist = []): Collection
    {
        $query = (new self())->whereIn(
            'status',
            [HostStatus::Initialization, HostStatus::Operational],
        );
        if (!empty($whitelist)) {
            $query->whereIn('address', $whitelist);
        }
        return $query->get();
    }

    public static function fetchUnreachableHostsForImportingInventory(array $whitelist = []): Collection
    {
        $query = (new self())->where('status', HostStatus::Unreachable);
        if (!empty($whitelist)) {
            $query->whereIn('address', $whitelist);
        }

        return $query->get()->filter(function ($networkHost) {
            /** @var self $networkHost */
            $hours = 2 ** max(0, $networkHost->failed_connection - config('app.inventory_failed_connection_limit'));
            return $hours <= self::MAXIMAL_PERIOD_FOR_SYNCHRONIZATION_RETRY_HOURS &&
                (
                    null === $networkHost->last_synchronization_attempt ||
                    (new DateTimeImmutable(sprintf('-%d hours', $hours)) > $networkHost->last_synchronization_attempt)
                );
        });
    }

    public function connectionSuccessful(): void
    {
        $now = new Carbon();
        $this->last_synchronization = $now;
        $this->last_synchronization_attempt = $now;
        $this->failed_connection = 0;
        $this->status = HostStatus::Operational;
        $this->update();
    }

    public function connectionFailed(): void
    {
        $this->last_synchronization_attempt = new Carbon();
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
