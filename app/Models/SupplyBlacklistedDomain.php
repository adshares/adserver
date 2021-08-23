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

namespace Adshares\Adserver\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

use function filter_var;
use function strpos;

/**
 * @property int id
 * @property string domain
 */
class SupplyBlacklistedDomain extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'domain',
    ];

    protected $visible = [
        'domain',
    ];

    public static function register(string $domain): void
    {
        if (self::where('domain', $domain)->first()) {
            return;
        }

        $model = new self(['domain' => $domain]);
        $model->save();
        Cache::forget('supply_blacklisted_domains');
    }

    public static function isDomainBlacklisted(string $domain): bool
    {
        if ('' === $domain || false === strpos($domain, '.') || false !== filter_var($domain, FILTER_VALIDATE_IP)) {
            return true;
        }

        $blacklisted = Cache::remember('supply_blacklisted_domains', 10, function () {
            return self::all()->pluck('domain', 'domain')->toArray();
        });

        $domainParts = explode('.', $domain);
        $domainPartsCount = count($domainParts);

        for ($i = 0; $i < $domainPartsCount; $i++) {
            if (array_key_exists(implode('.', $domainParts), $blacklisted)) {
                return true;
            }
            array_shift($domainParts);
        }

        return false;
    }
}
