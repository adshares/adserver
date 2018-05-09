<?php

namespace Adshares\Adserver\Models;

use Adshares\Adserver\ModelTraits\AccountAddress;
use Adshares\Adserver\ModelTraits\AutomateMutators;
use Adshares\Adserver\ModelTraits\BinHex;
use Adshares\Adserver\ModelTraits\JsonValue;
use Adshares\Adserver\ModelTraits\TransactionId;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use AccountAddress;
    use AutomateMutators;
    use BinHex;
    use JsonValue;
    use TransactionId;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
      'transfers', 'subthreshold_transfers',
      'account_address', 'account_hashin', 'account_hashout', 'account_msid',
      'tx_data', 'tx_id', 'tx_time', 'fee',
      'completed',
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
      'transfers' => 'JsonValue',
      'subthreshold_transfers' => 'JsonValue',
      'account_address' => 'AccountAddress',
      'account_hashin' => 'BinHex',
      'account_hashout' => 'BinHex',
      'tx_id' => 'TransactionId',
      'fee' => 'Money',
    ];
}
