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
        'user_id', 'name', 'url'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'deleted_at',
    ];

    public static $rules = [
        'name' => 'max:64',
        'url' => 'required|url',
    ];

    public function siteExcludes()
    {
        return $this->hasMany("Adshares\Adserver\Models\SiteExclude");
    }

    public function siteRequires()
    {
        return $this->hasMany( "Adshares\Adserver\Models\SiteRequire");
    }
}
