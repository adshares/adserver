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

use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\JsonValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int id
 * @property Carbon created_at
 * @property string case_id
 * @property int network_impression_id
 * @property string publisher_id
 * @property string site_id
 * @property string zone_id
 * @property string banner_id
 * @property NetworkImpression networkImpression
 * @mixin Builder
 */
class NetworkMissedCase extends Model
{
    use AutomateMutators;
    use BinHex;
    use HasFactory;
    use JsonValue;

    public const UPDATED_AT = null;

    protected $fillable = [
        'case_id',
        'publisher_id',
        'site_id',
        'zone_id',
        'banner_id',
    ];

    protected $visible = [];

    protected array $traitAutomate = [
        'case_id' => 'BinHex',
        'publisher_id' => 'BinHex',
        'site_id' => 'BinHex',
        'zone_id' => 'BinHex',
        'campaign_id' => 'BinHex',
        'banner_id' => 'BinHex',
    ];

    public static function create(
        string $caseId,
        string $publisherId,
        string $siteId,
        string $zoneId,
        string $bannerId,
    ): ?self {
        if (null !== self::fetchByCaseId($caseId)) {
            return null;
        }

        return new self(
            [
                'case_id' => $caseId,
                'publisher_id' => $publisherId,
                'site_id' => $siteId,
                'zone_id' => $zoneId,
                'banner_id' => $bannerId,
            ]
        );
    }

    public static function fetchByCaseId(string $caseId): ?NetworkMissedCase
    {
        return (new self())->where('case_id', hex2bin($caseId))->first();
    }
}
