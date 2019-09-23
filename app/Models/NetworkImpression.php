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

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\JsonValue;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Dto\UserContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use stdClass;
use function hex2bin;

/**
 * @property int id
 * @property int created_at
 * @property int updated_at
 * @property string impression_id
 * @property string tracking_id
 * @property string|null user_id
 * @property stdClass context
 * @property int|null human_score
 * @property string|null user_data
 * @property Collection networkCases
 * @mixin Builder
 */
class NetworkImpression extends Model
{
    use AutomateMutators;
    use BinHex;
    use JsonValue;

    /** @var array */
    protected $visible = [];

    /**
     * The attributes that use some Models\Traits with mutator settings automation
     *
     * @var array
     */
    protected $traitAutomate = [
        'impression_id' => 'BinHex',
        'tracking_id' => 'BinHex',
        'user_id' => 'BinHex',
        'context' => 'JsonValue',
        'user_data' => 'JsonValue',
    ];

    public static function register(
        string $impressionId,
        string $trackingId,
        ImpressionContext $context
    ): void {
        if (self::where('impression_id', hex2bin($impressionId))->first()) {
            return;
        }

        $log = new self();
        $log->impression_id = $impressionId;
        $log->tracking_id = $trackingId;
        $log->context = $context->toArray();

        $log->save();
    }

    public static function fetchByImpressionId(string $impressionId): ?NetworkImpression
    {
        return self::where('impression_id', hex2bin($impressionId))->first();
    }

    public function updateWithUserContext(UserContext $userContext): void
    {
        $userId = $userContext->userId();
        if ($userId) {
            $this->user_id = Uuid::fromString($userId)->hex();
        }
        $this->human_score = $userContext->humanScore();
        $this->user_data = $userContext->keywords();

        $this->save();
    }

    public function networkCases(): HasMany
    {
        return $this->hasMany(NetworkCase::class);
    }
}
