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

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Facades\DB;
use Adshares\Common\Exception\InvalidArgumentException;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property int id
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property Carbon|null deleted_at
 * @property string domain
 * @property int|null reject_reason_id
 * @mixin Builder
 */
class SitesRejectedDomain extends Model
{
    use HasFactory;
    use SoftDeletes;

    private const CACHE_KEY = 'sites_rejected_domain';
    private const CACHE_TTL = 10 * 60;

    protected $fillable = [
        'domain',
    ];

    protected $visible = [
        'domain',
    ];

    private static function upsert(string $domain): void
    {
        /** @var self $model */
        $model = self::withTrashed()->where('domain', $domain)->first();
        if (null === $model) {
            $model = new self(['domain' => $domain]);
        } else {
            $model->restore();
        }
        $model->save();
    }

    /**
     * @return array<string>
     */
    public static function fetchAll(): array
    {
        return self::all()->pluck('domain')->toArray();
    }

    public static function storeDomains(array $domains): void
    {
        /** @var Collection<self> $databaseDomains */
        $databaseDomains = self::all();
        $idsToDelete = [];
        foreach ($databaseDomains as $databaseDomain) {
            if (!in_array($databaseDomain->domain, $domains)) {
                $idsToDelete[] = $databaseDomain->id;
            }
        }

        DB::beginTransaction();
        try {
            self::whereIn('id', $idsToDelete)->delete();
            foreach ($domains as $domain) {
                SitesRejectedDomain::upsert((string)$domain);
            }
            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
        Cache::forget(self::CACHE_KEY);
    }

    public static function getMatchingRejectedDomain(string $domain): ?string
    {
        if ('' === $domain || !str_contains($domain, '.') || false !== filter_var($domain, FILTER_VALIDATE_IP)) {
            return $domain;
        }

        $rejected = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn () => self::all()->pluck('reject_reason_id', 'domain')->toArray(),
        );

        $domainParts = explode('.', $domain);
        $domainPartsCount = count($domainParts);

        for ($i = 0; $i < $domainPartsCount; $i++) {
            if (array_key_exists(implode('.', $domainParts), $rejected)) {
                return implode('.', $domainParts);
            }
            array_shift($domainParts);
        }

        return null;
    }

    public static function isDomainRejected(string $domain): bool
    {
        return null !== self::getMatchingRejectedDomain($domain);
    }

    public static function domainRejectedReasonId(string $domain): ?int
    {
        if ('' === $domain || !str_contains($domain, '.') || false !== filter_var($domain, FILTER_VALIDATE_IP)) {
            return null;
        }

        $rejected = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn () => self::all()->pluck('reject_reason_id', 'domain')->toArray(),
        );

        $domainParts = explode('.', $domain);
        $domainPartsCount = count($domainParts);

        for ($i = 0; $i < $domainPartsCount; $i++) {
            if (array_key_exists(implode('.', $domainParts), $rejected)) {
                return $rejected[implode('.', $domainParts)] ?? null;
            }
            array_shift($domainParts);
        }

        throw new InvalidArgumentException(sprintf('Domain %s is not rejected', $domain));
    }
}
