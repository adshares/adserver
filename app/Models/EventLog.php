<?php

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

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
      'cid', 'tid',
      'publisher_event_id', 'banner_id',
      'event_type',
      'pay_to', 'ip',
      'our_context', 'their_context',
      'user_id', 'human_score', 'our_userdata', 'their_userdata',
      'timestamp',
      'event_value', 'paid_amount', 'payment_id'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
    ];

    /**
    * The attributes that use some Models\Traits with mutator settings automation
    *
    * @var array
    */
    protected $traitAutomate = [
      'cid' => 'BinHex',
      'tid' => 'BinHex',
      'pay_to' => 'AccountAddress',
      'ip' => 'BinHex',
      'our_context' => 'JsonValue',
      'their_context' => 'JsonValue',
      'user_id' => 'BinHex',
      'our_userdata' => 'JsonValue',
      'their_userdata' => 'JsonValue',
      'event_value' => 'Money',
      'paid_amount' => 'Money',
    ];
}
