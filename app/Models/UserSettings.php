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

    private const NOTIFY = 'notify';
    private const EMAIL = 'email';

    public static $default_notifications = [
        'billing' => [self::EMAIL => true, self::NOTIFY => true],
        'maintenance' => [self::EMAIL => true, self::NOTIFY => true],
        'newsletter' => [self::EMAIL => true, self::NOTIFY => true],
        'tips' => [self::EMAIL => true, self::NOTIFY => true],
        'offers' => [self::EMAIL => true, self::NOTIFY => true],
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
