<?php

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Models\Contracts\Camelizable;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\Serialize;
use Adshares\Adserver\Models\Traits\ToArrayCamelize;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notification extends Model implements Camelizable
{
    use SoftDeletes;

    use AutomateMutators;
    use Serialize;
    use ToArrayCamelize;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'userRole', 'type', 'title', 'message', 'payload',
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
        'payload' => 'Serialize',
    ];
}
