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

namespace Adshares\Adserver\Models;

use Adshares\Common\Domain\ValueObject\SecureUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use function route;

class ConversionDefinition extends Model
{
    private const IN_BUDGET = 'in_budget';
    private const OUT_OF_BUDGET = 'out_of_budget';

    private const ALLOWED_BUDGET_TYPES = [
        self::IN_BUDGET,
        self::OUT_OF_BUDGET,
    ];

    protected $fillable = [
        'id',
        'campaign_id',
        'name',
        'budget_type',
        'event_type',
        'type',
        'value',
        'limit',
    ];

    protected $visible = [
        'id',
        'campaign_id',
        'name',
        'budget_type',
        'event_type',
        'type',
        'value',
        'limit',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function conversionGroups(): HasMany
    {
        return $this->hasMany(ConversionGroup::class);
    }

    public static function generateLink(int $definitionConvertionId): string
    {
        $params = [
            'conversion_id' => $definitionConvertionId,
            'cid' => '',
            'value' => '',
            'tnonce' => '',
            'sig' => '',
        ];

        return (new SecureUrl(route('conversion', $params)))->toString();
    }

    public static function removeWithoutGiven(array $ids): void
    {
        self::whereNotIn('id', $ids)->delete();
    }
}
