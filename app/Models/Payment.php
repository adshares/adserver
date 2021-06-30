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

use Adshares\Adserver\Models\Traits\AccountAddress;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\JsonValue;
use Adshares\Adserver\Models\Traits\TransactionId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

use function hex2bin;

/**
 * @mixin Builder
 * @property int id
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property Carbon|null deleted_at
 * @property string account_address
 * @property string|null account_hashin
 * @property string|null account_hashout
 * @property string|null account_msid
 * @property string|null tx_data
 * @property string|null tx_id
 * @property int|null tx_time
 * @property int fee
 * @property string state
 * @property Collection|EventLog[] events
 */
class Payment extends Model
{
    use AccountAddress;
    use AutomateMutators;
    use BinHex;
    use JsonValue;
    use TransactionId;

    public const STATE_NEW = 'new';

    public const STATE_SENT = 'sent';

    public const STATE_SUCCESSFUL = 'ok';

    public const STATE_FAILED = 'failed';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'transfers',
        'subthreshold_transfers',
        'account_address',
        'account_hashin',
        'account_hashout',
        'account_msid',
        'tx_data',
        'tx_id',
        'tx_time',
        'fee',
        'completed',
        'state',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that use some Models\Traits with mutator settings automation
     *
     * @var array
     */
    protected $traitAutomate = [
        'transfers' => 'JsonValue',
        'subthreshold_transfers' => 'JsonValue',
        'account_address' => 'AccountAddress',
        'account_hashin' => 'BinHex',
        'account_hashout' => 'BinHex',
        'tx_id' => 'TransactionId',
    ];

    public static function fetchPayments(string $transactionId, string $accountAddress): Collection
    {
        return self::where('tx_id', hex2bin($transactionId))
            ->where('account_address', hex2bin($accountAddress))
            ->get();
    }

    public static function fetchByStatus(string $state, bool $completed): Collection
    {
        return self::where('state', $state)
            ->where('completed', $completed)
            ->get();
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(Conversion::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(EventLog::class);
    }

    public function transferableAmount(): int
    {
        return $this->fee ?? 0;
    }
}
