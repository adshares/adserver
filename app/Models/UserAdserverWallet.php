<?php

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Models\Contracts\Camelizable;
use Adshares\Adserver\Models\Traits\AccountAddress;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\ToArrayCamelize;
use Illuminate\Database\Eloquent\Model;

class UserAdserverWallet extends Model implements Camelizable
{
    use AccountAddress;
    use AutomateMutators;
    use ToArrayCamelize;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'adshares_address',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'id', 'user_id',
    ];

    /**
     * Custom table name.
     *
     * @var string
     */
    protected $table = 'users_adserver_wallets';

    /**
     * The attributes that use some Models\Traits with mutator settings automation.
     *
     * @var array
     */
    protected $traitAutomate = [
        'adshares_address' => 'AccountAddress',
    ];
}
