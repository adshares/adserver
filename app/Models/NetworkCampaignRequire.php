<?php

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;

use Illuminate\Database\Eloquent\Model;

class NetworkCampaignRequire extends Model
{
    use AutomateMutators;
    use BinHex;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
      'uuid', 'network_campaign_id',
      'source_created_at', 'source_updated_at',
      'name', 'min', 'max'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
      'id', 'network_campaign_id'
    ];

    /**
     * The attributes that use some Models\Traits with mutator settings automation
     *
     * @var array
     */
    protected $traitAutomate = [
      'uuid' => 'BinHex',
    ];
}
