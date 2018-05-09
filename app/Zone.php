<?php

namespace Adshares\Adserver;

use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
      'website_id', 'name', 'width', 'height',
    ];
}
