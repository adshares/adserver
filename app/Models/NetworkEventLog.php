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

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Traits\AccountAddress;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\JsonValue;
use Adshares\Adserver\Models\Traits\Money;
use Illuminate\Database\Eloquent\Model;

class NetworkEventLog extends Model
{
    use AccountAddress;
    use AutomateMutators;
    use BinHex;
    use JsonValue;
    use Money;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'cid',
        'tid',
        'banner_id',
        'pay_from',
        'event_type',
        'pay_to',
        'ip',
        'context',
        'user_id',
        'human_score',
        'our_userdata',
        'their_userdata',
        'timestamp',
        'event_value',
        'paid_amount',
        'payment_id',
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
        'cid' => 'BinHex',
        'tid' => 'BinHex',
        'banner_id' => 'BinHex',
        'pay_from' => 'AccountAddress',
        'ip' => 'BinHex',
        'context' => 'JsonValue',
        'user_id' => 'BinHex',
        'our_userdata' => 'JsonValue',
        'their_userdata' => 'JsonValue',
        'event_value' => 'Money',
        'paid_amount' => 'Money',
    ];

    public function getAdselectJson()
    {
        return [
            'event_id' => (string)$this->id,
            'banner_id' => (string)$this->banner_id,
            'keywords' => Utils::flattenKeywords($this->getKeywords()),
            'paid_amount' => $this->event_value,
            'user_id' => $this->user_id,
            'publisher_id' => "1",
            'human_score' => $this->human_score,
        ];
    }

    public function getKeywords()
    {
        $data = array_merge(
            (array)$this->context,
            [
                'user' => $this->our_userdata,
            ]
        );

        return $data;
    }
}
