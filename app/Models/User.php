<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Events\GenerateUUID;
use Adshares\Adserver\Events\UserCreated;
use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use Notifiable;
    use SoftDeletes;

    use AutomateMutators;
    use BinHex;

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
        'is_advertiser',
        'is_publisher',
    ];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $visible = [
        'uuid',
        'email',
        'name',
        'is_advertiser',
        'is_publisher',
        'is_admin',
        'api_token',
        'is_email_confirmed',
        'adserver_wallet',
    ];
    /**
     * The attributes that use some Models\Traits with mutator settings automation.
     *
     * @var array
     */
    protected $traitAutomate = [
        'uuid' => 'BinHex',
    ];
    protected $appends = [
        'adserver_wallet',
        'is_email_confirmed',
    ];

    public static function register($data)
    {
        $user = new User($data);
        $user->password = $data['password'];
        $user->email = $data['email'];
        $user->save();

        return $user;
    }

    public function getIsEmailConfirmedAttribute()
    {
        return (bool)$this->created_at;
    }

    public function getAdserverWalletAttribute()
    {
// UserLedgerEntry::where('user_id', $this->id);
// TODO reduce to single array

        return [
            "total_funds" => "110.000000000",
            "total_funds_in_currency" => "0.00",
            "total_funds_change" => "0.000000000",
            "last_payment_at" => null,
        ];
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = null !== $value ? Hash::make($value) : null;
    }

    public function validPassword($value)
    {
        return Hash::check($value, $this->attributes['password']);
    }

    public function setRememberToken($token)
    {
        return;
    }

    public function generateApiKey()
    {
        do {
            $this->api_token = str_random(60);
        } while ($this->where('api_token', $this->api_token)->exists());

        $this->save();
    }

    public function clearApiKey()
    {
        $this->api_token = null;
        $this->save();
    }

    /**
     * check toArrayExtrasCheck() in AutomateMutators trait.
     */
    protected function toArrayExtras($array)
    {
        $array['is_email_confirmed'] = !empty($array['email_confirmed_at']);

        return $array;
    }
}
