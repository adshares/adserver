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
use Illuminate\Database\Eloquent\Model;

class EventLog extends Model
{
    use AccountAddress;
    use AutomateMutators;
    use BinHex;
    use JsonValue;

    public const TYPE_REQUEST = 'request';

    public const TYPE_VIEW = 'view';

    public const TYPE_CLICK = 'click';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'event_id',
        'user_id',
        'banner_id',
        'zone_id',
        'event_type',
        'pay_to',
        'ip',
        'headers',
        'our_context',
        'their_context',
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
     * The attributes that use some Models\Traits with mutator settings automation.
     *
     * @var array
     */
    protected $traitAutomate = [
        'event_id' => 'BinHex',
        'user_id' => 'BinHex',
        'banner_id' => 'BinHex',
        'pay_to' => 'AccountAddress',
        'ip' => 'BinHex',
        'headers' => 'JsonValue',
        'our_context' => 'JsonValue',
        'their_context' => 'JsonValue',
        'our_userdata' => 'JsonValue',
        'their_userdata' => 'JsonValue',
    ];

    public function getAdpayJson()
    {
        return [
            'event_id' => (string)$this->event_id,
            'event_type' => $this->event_type,
            'event_value' => $this->event_value,
            'banner_id' => (string)$this->banner_id,
            'zone_id' => $this->zone_id,
            'our_keywords' => Utils::flattenKeywords($this->getOurKeywords()),
            'their_keywords' => Utils::flattenKeywords($this->getTheirKeywords()),
            'timestamp' => $this->updated_at,
            'user_id' => $this->user_id,
            'advertiser_id' => 1, // TODO: chat with Jacek
            'human_score' => $this->human_score,
        ];
    }

    public function getOurKeywords()
    {
        return array_merge(
            (array)$this->our_context,
            [
                'user' => $this->our_userdata,
            ]
        );
    }

    public function getTheirKeywords()
    {
        return array_merge(
            (array)$this->their_context,
            [
                'user' => $this->their_userdata,
            ]
        );

        return $data;
    }
}
