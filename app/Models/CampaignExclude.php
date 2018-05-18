<?php

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Events\GenerateUUID;

use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;

use Illuminate\Database\Eloquent\Model;

class CampaignExclude extends Model
{
    use AutomateMutators;
    use BinHex;

    /**
     * The event map for the model.
     *
     * @var array
     */
    protected $dispatchesEvents = [
      'creating' => GenerateUUID::class,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
      'uuid', 'campaign_id', 'name', 'min', 'max'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
      'id','campaign_id'
    ];

    /**
     * The attributes that use some Models\Traits with mutator settings automation
     *
     * @var array
     */
    protected $traitAutomate = [
      'uuid' => 'BinHex',
    ];

    public function campaign()
    {
        return $this->belongsTo('Adshares\Adserver\Models\Campaign');
    }
}
