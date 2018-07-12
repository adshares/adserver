<?php

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Events\GenerateUUID;
use Adshares\Adserver\Events\UserCreated;
use Adshares\Adserver\Models\Contracts\Camelizable;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\ToArrayCamelize;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable implements Camelizable
{
    use Notifiable;
    use SoftDeletes;

    use AutomateMutators;
    use BinHex;
    use ToArrayCamelize;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

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
        'password',
    ];

    public static $rules = [
        'email' => 'email|max:150|unique:users',
        'password' => 'min:8',
        'password_new' => 'min:8',
        'is_advertiser' => 'boolean',
        'is_publisher' => 'boolean',
    ];

    public static $rules_add = [
        'email' => 'required|email|max:150|unique:users',
        'password' => 'required|min:8',
        'is_advertiser' => 'boolean',
        'is_publisher' => 'boolean',
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

    public static function register($data)
    {
        $user = new User($data);
        $user->password = $data['password'];
        $user->email = $data['email'];
        $user->save();

        return $user;
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

    public function validPassword($value)
    {
        return Hash::check($value, $this->attributes['password']);
    }

    public function setRememberToken($token)
    {
        return;
    }
}
