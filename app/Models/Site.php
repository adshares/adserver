<?php

namespace Adshares\Adserver\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Site.
 *
 * @property int user_id
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
        'user_id', 'name',
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
        'name' => 'required|max:64',
    ];

    public function siteExcludes()
    {
        return $this->hasMany("Adshares\Adserver\Models\SiteExclude");
    }

    public function siteRequires()
    {
        return $this->hasMany("Adshares\Adserver\Models\SiteRequire");
    }
}
