<?php

namespace Adshares\Adserver\Models;

use Illuminate\Database\Eloquent\Model;

abstract class SiteTargeting extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'key', 'value'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
    ];

    public function site()
    {
        return $this->belongsTo("App\Site");
    }
}
