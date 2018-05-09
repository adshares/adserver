<?php

namespace Adshares\Adserver\Models;

use Adshares\Adserver\ModelTraits\AccountAddress;
use Adshares\Adserver\ModelTraits\AutomateMutators;

use Illuminate\Database\Eloquent\Model;

class NetworkHost extends Model
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
      'address', 'host', 'last_seen', 'banner_id',
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
      'address' => 'AccountAddress',
    ];
}
