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

namespace Adshares\Adserver\Models;

use DateTime;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

/**
 * @property string base_url
 */
class ServeDomain extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'base_url',
    ];

    protected $visible = [
        'base_url',
    ];

    public static function upsert(string $baseUrl): void
    {
        $serveDomain = ServeDomain::where('base_url', $baseUrl)->first();
        if (null === $serveDomain) {
            $serveDomain = new self();
            $serveDomain->base_Url = $baseUrl;
        }
        $serveDomain->updated_at = new DateTime();
        $serveDomain->save();
        Cache::forget('serve-domain.current');
    }

    public static function changeUrlHost(string $url): string
    {
        return preg_replace('#([a-z]+\:\/\/)(.*?)/#i', self::current() . '/', $url);
    }

    public static function current(?string $subDomain = null): string
    {
        return Cache::remember('serve-domain.current.' . $subDomain, 60, function () use ($subDomain) {
            $domain = ServeDomain::orderBy('id', 'DESC')->first();
            $url = $domain !== null ? $domain->base_url : config('app.serve_base_url');
            if (!empty($subDomain)) {
                $url = str_replace('://', "://$subDomain.", $url);
            }
            return $url;
        });
    }

    public static function fetch(): array
    {
        return ServeDomain::orderBy('updated_at', 'DESC')->get()->pluck('base_url')->toArray();
    }

    public static function clear(): void
    {
        ServeDomain::where('updated_at', '<', new DateTime('-30 days'))->delete();
    }
}
