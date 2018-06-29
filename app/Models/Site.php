<?php

namespace Adshares\Adserver\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Site
 * @package Adshares\Adserver\Models
 *
 * @property integer user_id
 * @property string name
 * @property string url
 */
class Site extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id', 'name', 'url',
    ];

    public static $rules = [
        'name' => 'max:64',
        'url' => 'required|url',
    ];
}
