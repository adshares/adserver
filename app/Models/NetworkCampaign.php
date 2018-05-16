<?php

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Models\Traits\AutomateMutators;
use Adshares\Adserver\Models\Traits\BinHex;

use Illuminate\Database\Eloquent\Model;

class NetworkCampaign extends Model
{
    use AutomateMutators;
    use BinHex;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
      'uuid',
      'source_host', 'source_update_time', 'adshares_address',
      'landing_url', 'max_cpm', 'max_cpc', 'budget_per_hour', 'time_start', 'time_end',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
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
