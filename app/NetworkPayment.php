<?php

namespace Adshares\Adserver;

use Adshares\Adserver\ModelTraits\AccountAddress;
use Adshares\Adserver\ModelTraits\AutomateMutators;
use Adshares\Adserver\ModelTraits\BinHex;
use Adshares\Adserver\ModelTraits\JsonValue;

use Illuminate\Database\Eloquent\Model;

class NetworkPayment extends Model
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
      'receiver_address', 'sender_address', 'sender_host',
      'amount', 'account_hashout', 'account_msid',
      'tx_id', 'tx_time',
      'detailed_data_used',
      'processed',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
    ];

    /**
    * The attributes that use some ModelTraits with mutator settings automation
    *
    * @var array
    */
    protected $traitAutomate = [
      'receiver_address' => 'AccountAddress',
      'sender_address' => 'AccountAddress',
    ];
}
