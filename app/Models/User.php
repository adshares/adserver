<?php

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Events\GenerateUUID;
use Adshares\Adserver\Events\UserCreated;
use Adshares\Adserver\Models\Contracts\Camelizable;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\ToArrayCamelize;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable implements Camelizable
{
    use Notifiable;

    use AutomateMutators;
    use BinHex;
    use ToArrayCamelize;

    /**
     * The event map for the model.
     *
     * @var array
     */
    protected $dispatchesEvents = [
        'creating' => GenerateUUID::class,
        'created' => UserCreated::class,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'is_advertiser', 'is_publisher',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'id', 'password', 'remember_token',
    ];

    public static $rules = [
        'email' => 'email|max:150|unique:users',
        'password' => 'min:8',
        'is_advertiser' => 'boolean',
        'is_publisher' => 'boolean',
    ];

    public static $rules_add = [
        'email' => 'required|email|max:150|unique:users',
        'password' => 'required|min:8',
        'is_advertiser' => 'boolean',
        'is_publisher' => 'boolean',
    ];

    public static $rules_email_activate = [
        'email_confirm_token' => 'required',
    ];

    /**
     * The attributes that use some Models\Traits with mutator settings automation.
     *
     * @var array
     */
    protected $traitAutomate = [
        'uuid' => 'BinHex',
    ];

    public function adserverWallet()
    {
        return $this->hasOne('Adshares\Adserver\Models\UserAdserverWallet');
    }

    public function checkPassword($value)
    {
        return Hash::check($this->attributes['password'], $value);
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = null !== $value ? Hash::make($value) : null;
    }

    /**
     * check toArrayExtrasCheck() in AutomateMutators trait.
     */
    protected function toArrayExtras($array)
    {
        $array['isEmailConfirmed'] = !empty($array['email_confirmed_at']);

        return $array;
    }
}
