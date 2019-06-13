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
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;
use function route;

class ConversionDefinition extends Model
{
    use AutomateMutators;
    use BinHex;

    private const IN_BUDGET = 'in_budget';
    private const OUT_OF_BUDGET = 'out_of_budget';

    public const BASIC_TYPE = 'basic';
    public const ADVANCED_TYPE = 'advanced';

    public const ALLOWED_TYPES = [
        self::BASIC_TYPE,
        self::ADVANCED_TYPE,
    ];

    private const CLICK_CONVERSION = 'click';

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

    protected $traitAutomate = [
        'uuid' => 'BinHex',
    ];

    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
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

    public static function removeFromCampaignWithoutGivenIds(int $campaignId, array $ids): void
    {
        self::where('campaign_id', $campaignId)
            ->whereNotIn('id', $ids)->delete();
    }

    public static function isClickConversionForCampaign(int $campaignId): bool
    {
        return (bool) self::where('campaign_id', $campaignId)
            ->where('event_type', self::CLICK_CONVERSION)
            ->first();
    }

    public static function rules(array $conversion): array
    {
        $type = $conversion['type'] ?? null;
        $eventType = $conversion['event_type'] ?? null;
        $rules = [
            'id' => 'integer|nullable',
            'campaign_id' => 'required|integer',
            'name' => 'required|max:255',
            'event_type' => 'required|max:50',
            'type' => sprintf('required|in:%s', implode(',', self::ALLOWED_TYPES)),
            'value' => [
                'integer',
                'nullable',
                Rule::requiredIf(static function () use ($type, $eventType) {
                    return $type === self::BASIC_TYPE && $eventType !== self::CLICK_CONVERSION;
                }),
            ],
            'limit' => 'integer|nullable',
        ];

        if ($type === self::BASIC_TYPE) {
            $rules['budget_type'] = 'in:'.self::IN_BUDGET;
        } elseif ($type === self::ADVANCED_TYPE) {
            $rules['budget_type'] = sprintf('in:%s,%s', self::IN_BUDGET, self::OUT_OF_BUDGET);
        }

        return $rules;
    }
}
