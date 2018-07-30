<?php

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Models\Contracts\Camelizable;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\Serialize;
use Adshares\Adserver\Models\Traits\ToArrayCamelize;
use Illuminate\Database\Eloquent\Model;

class UserSettings extends Model implements Camelizable
{
    use AutomateMutators;
    use Serialize;
    use ToArrayCamelize;

    public static $default_notifications = [
        'billing' => ['email' => true, 'notify' => true],
        'maintenance' => ['email' => true, 'notify' => true],
        'newsletter' => ['email' => true, 'notify' => true],
        'tips' => ['email' => true, 'notify' => true],
        'offers' => ['email' => true, 'notify' => true],
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'type', 'payload',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * Custom table name.
     *
     * @var string
     */
    protected $table = 'users_settings';

    /**
     * The attributes that use some Models\Traits with mutator settings automation.
     *
     * @var array
     */
    protected $traitAutomate = [
        'payload' => 'Serialize',
    ];
}
