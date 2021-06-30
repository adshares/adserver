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

use Adshares\Adserver\Events\GenerateUUID;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;

use function hex2bin;
use function route;

/**
 * @property int id
 * @property string uuid
 * @property int campaign_id
 * @property string name
 * @property string $limit_type
 * @property string event_type
 * @property string type
 * @property int|null value
 * @property bool is_value_mutable
 * @property bool is_repeatable
 * @property int cost
 * @property int occurrences
 * @property string link
 */
class ConversionDefinition extends Model
{
    use AutomateMutators;
    use BinHex;
    use SoftDeletes;

    private const IN_BUDGET = 'in_budget';

    private const OUT_OF_BUDGET = 'out_of_budget';

    public const BASIC_TYPE = 'basic';

    public const ADVANCED_TYPE = 'advanced';

    public const ALLOWED_TYPES = [
        self::BASIC_TYPE,
        self::ADVANCED_TYPE,
    ];

    protected $fillable = [
        'campaign_id',
        'name',
        'limit_type',
        'event_type',
        'type',
        'value',
        'is_value_mutable',
        'is_repeatable',
    ];

    protected $visible = [
        'uuid',
        'campaign_id',
        'name',
        'limit_type',
        'event_type',
        'type',
        'value',
        'is_value_mutable',
        'is_repeatable',
        'link',
        'cost',
        'occurrences',
    ];

    protected $appends = [
        'link',
    ];

    protected $casts = [
        'is_value_mutable' => 'boolean',
        'is_repeatable' => 'boolean',
    ];

    protected $traitAutomate = [
        'uuid' => 'BinHex',
    ];

    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
    ];

    public static function fetchById(int $id): ?self
    {
        return self::find($id);
    }

    public static function fetchByUuid(string $uuid): ?self
    {
        return self::where('uuid', hex2bin($uuid))->first();
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function conversionGroups(): HasMany
    {
        return $this->hasMany(Conversion::class);
    }

    public function getLinkAttribute(): string
    {
        $params = [
            'uuid' => $this->uuid,
        ];

        if (self::ADVANCED_TYPE === $this->type) {
            $params = array_merge(
                $params,
                [
                    'value' => 'value',
                    'nonce' => 'nonce',
                    'ts' => 'timestamp',
                    'sig' => 'signature',
                ]
            );
        }

        return (new SecureUrl(route('conversion.gif', $params)))->toString();
    }

    public function isAdvanced(): bool
    {
        return self::ADVANCED_TYPE === $this->type;
    }

    public function isInCampaignBudget(): bool
    {
        return self::IN_BUDGET === $this->limit_type;
    }

    public static function updateCostAndOccurrences(array $costAndOccurrencesArray): void
    {
        $ids = array_keys($costAndOccurrencesArray);
        $definitions = ConversionDefinition::whereIn('id', $ids)->get();

        foreach ($definitions as $definition) {
            $data = $costAndOccurrencesArray[$definition->id];

            $definition->cost = $data['cost'];
            $definition->occurrences = $data['occurrences'];
            $definition->save();
        }
    }

    public static function rules(array $conversion): array
    {
        $type = $conversion['type'] ?? null;
        $isValueMutable = (bool)($conversion['is_value_mutable'] ?? false);
        $rules = [
            'uuid' => 'string|nullable',
            'name' => 'required|max:255',
            'event_type' => 'required|max:50',
            'type' => sprintf('required|in:%s', implode(',', self::ALLOWED_TYPES)),
            'value' => [
                'integer',
                'min:0',
                'nullable',
                Rule::requiredIf(static function () use ($isValueMutable) {
                    return !$isValueMutable;
                }),
            ],
            'is_value_mutable' => 'required|boolean',
        ];

        if ($type === self::BASIC_TYPE) {
            $rules['limit_type'] = 'required|in:' . self::IN_BUDGET;
            $rules['is_repeatable'] = [
                'required',
                Rule::in(false, 0),
            ];
        } elseif ($type === self::ADVANCED_TYPE) {
            $rules['limit_type'] = sprintf('required|in:%s,%s', self::IN_BUDGET, self::OUT_OF_BUDGET);
            $rules['is_repeatable'] = 'required|boolean';
        }

        return $rules;
    }
}
