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

use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int id
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property Carbon|null deleted_at
 * @property string domain
 * @mixin Builder
 */
class SitesRejectedDomain extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'domain',
    ];

    protected $visible = [
        'domain',
    ];

    public static function upsert(string $domain): void
    {
        $model = self::where('domain', $domain)->first();
        if (null === $model) {
            $model = new self(['domain' => $domain]);
        } else {
            $model->updated_at = new DateTime();
        }
        $model->save();
    }

    public static function deleteByIds(array $ids): void
    {
        self::whereIn('id', $ids)->delete();
    }

    public static function fetchAll(): Collection
    {
        return self::all();
    }

    public static function isDomainRejected(string $domain): bool
    {
        $domainParts = explode('.', $domain);
        if (!$domainParts) {
            return true;
        }
        $domainPartsCount = count($domainParts);
        if ($domainPartsCount < 2) {
            return false;
        }

        array_shift($domainParts);
        --$domainPartsCount;

        $domains = [];
        for ($i = 0; $i < $domainPartsCount; $i++) {
            $domains[] = implode('.', $domainParts);
            array_shift($domainParts);
        }

        return self::whereIn('domain', $domains)->get()->count() > 0;
    }
}
