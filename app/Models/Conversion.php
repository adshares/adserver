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

use Adshares\Adserver\Events\GenerateUUID;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int id
 * @property string uuid
 * @property Carbon created_at
 * @property EventLog event
 * @property int event_logs_id
 * @property string group_id
 * @property int conversion_definition_id
 * @property int value
 * @property float weight
 * @property int payment_status
 */
class Conversion extends Model
{
    use AutomateMutators;
    use BinHex;

    protected $fillable = [
        'uuid',
        'group_id',
        'event_logs_id',
        'conversion_definition_id',
        'value',
        'weight',
    ];

    protected $visible = [
        'uuid',
        'group_id',
        'event_logs_id',
        'conversion_definition_id',
        'value',
        'weight',
    ];

    protected $traitAutomate = [
        'uuid' => 'BinHex',
        'group_id' => 'BinHex',
    ];

    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
    ];

    public static function register(
        string $groupId,
        int $eventId,
        int $conversionDefinitionId,
        int $value,
        float $weight
    ): void {
        $group = new self();
        $group->group_id = $groupId;
        $group->event_logs_id = $eventId;
        $group->conversion_definition_id = $conversionDefinitionId;
        $group->value = $value;
        $group->weight = $weight;

        $group->save();
    }

    public static function wasRegisteredForDefinitionAndCaseId(int $conversionDefinitionId, array $caseIds): bool
    {
        $binaryCaseIds = array_map(
            function (string $item) {
                return hex2bin($item);
            },
            $caseIds
        );

        return null !== self::where('conversion_definition_id', $conversionDefinitionId)->whereIn(
                    'case_id',
                    $binaryCaseIds
                )->first();
    }

    public function conversionDefinition(): BelongsTo
    {
        return $this->belongsTo(ConversionDefinition::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(EventLog::class, 'event_logs_id', 'id');
    }
}
