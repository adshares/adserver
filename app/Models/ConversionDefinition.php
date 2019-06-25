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

use Adshares\Adserver\Events\ConversionDefinitionCreating;
use Adshares\Adserver\Events\GenerateUUID;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\Rule;
use function hex2bin;
use function route;

/**
 * @property int id
 * @property string uuid
 * @property int campaign_id
 * @property string name
 * @property string budget_type
 * @property string event_type
 * @property string type
 * @property int|null value
 * @property int|null limit
 * @property bool is_repeatable
 * @property string|null secret
 */
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
        'campaign_id',
        'name',
        'budget_type',
        'event_type',
        'type',
        'value',
        'limit',
        'is_repeatable',
    ];

    protected $visible = [
        'uuid',
        'campaign_id',
        'name',
        'budget_type',
        'event_type',
        'type',
        'value',
        'limit',
        'is_repeatable',
        'link',
        'secret',
    ];

    protected $appends = [
        'link',
    ];

    protected $traitAutomate = [
        'uuid' => 'BinHex',
    ];

    protected $dispatchesEvents = [
        'creating' => ConversionDefinitionCreating::class,
    ];

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
        return $this->hasMany(ConversionGroup::class);
    }

    public function getLinkAttribute()
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

    public function isAdvanced() {
        return self::ADVANCED_TYPE === $this->type;
    }

    public function isClickConversion() {
        return self::CLICK_CONVERSION === $this->event_type;
    }

    public function isRepeatable(): bool
    {
        return (bool)$this->is_repeatable;
    }

    public static function removeFromCampaignWithoutGivenUuids(int $campaignId, array $uuids): void
    {
        $binaryUuids = array_map(
            function (string $item) {
                return hex2bin($item);
            },
            $uuids
        );

        self::where('campaign_id', $campaignId)
            ->whereNotIn('uuid', $binaryUuids)->delete();
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
            'uuid' => 'string|nullable',
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