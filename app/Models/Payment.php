<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

use Adshares\Adserver\Models\Traits\AccountAddress;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\JsonValue;
use Adshares\Adserver\Models\Traits\TransactionId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use function count;
use function hex2bin;

/**
 * @mixin Builder
 * @property int event_value
 * @property int event_id
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

    public static function fetchPayments(string $transactionId, string $accountAddress)
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

    public function events(): HasMany
    {
        return $this->hasMany(EventLog::class);
    }

    public function totalLicenseFee(): int
    {
        return $this->events->sum(static function (EventLog $entry) {
            return $entry->license_fee;
        });
    }

    public function transferableAmount(): int
    {
        return $this->netAmount() ?? $this->fee ?? 0;
    }

    public function netAmount(): ?int
    {
        if (!count($this->events)) {
            return null;
        }

        return $this->events->sum(function (EventLog $entry) {
            return $entry->paid_amount;
        });
    }
}
