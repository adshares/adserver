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

use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversionGroup extends Model
{
    use AutomateMutators;
    use BinHex;

    public $timestamps = false;

    protected $fillable = [
        'case_id',
        'group_id',
        'event_logs_id',
        'conversion_definition_id',
        'value',
        'weight',
    ];

    protected $visible = [
        'case_id',
        'group_id',
        'event_logs_id',
        'conversion_definition_id',
        'value',
        'weight',
    ];

    protected $traitAutomate = [
        'case_id' => 'BinHex',
        'group_id' => 'BinHex',
    ];

    public static function register(
        string $caseId,
        string $groupId,
        int $eventId,
        int $conversionDefinitionId,
        int $value,
        float $weight
    ): void {
        $group = new self();
        $group->case_id = $caseId;
        $group->group_id = $groupId;
        $group->event_logs_id = $eventId;
        $group->conversion_definition_id = $conversionDefinitionId;
        $group->value = $value;
        $group->weight = $weight;
        $group->created_at = Carbon::now();

        $group->save();
    }

    public static function containsConversionMatchingCaseIds(int $conversionDefinitionId, array $caseIds): bool
    {
        $binaryCaseIds = array_map(
            function (string $item) {
                return hex2bin($item);
            },
            $caseIds
        );

        return null !== ConversionGroup::where('conversion_definition_id', $conversionDefinitionId)
            ->whereIn('case_id', $binaryCaseIds)
            ->first();
    }

    public function conversionDefinition(): BelongsTo
    {
        return $this->belongsTo(ConversionDefinition::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(EventConversionLog::class);
    }
}
