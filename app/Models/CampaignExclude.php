<?php

namespace Adshares\Adserver\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignExclude extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
      'name', 'min', 'max'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
       'id','campaign_id'
     ];

    public function campaign()
    {
        return $this->belongsTo('Adshares\Adserver\Models\Campaign');
    }
}
