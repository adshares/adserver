<?php

namespace Adshares\Adserver;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'login', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    public function checkPassword($value)
    {
        return Hash::check($this->attributes['password'], $value);
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = $value !== null ? Hash::make($value) : null;
    }
}
