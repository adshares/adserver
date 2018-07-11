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
        'deleted_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'value' => 'array'
    ];

    /**
     * All of the relationships to be touched.
     *
     * @var array
     */
    protected $touches = ['site'];

    public function site()
    {
        return $this->belongsTo("Adshares\Adserver\Models\Site");
    }
}
