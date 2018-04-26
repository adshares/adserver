<?php

namespace App;

use App\Events\GenerateUUID;

use App\ModelTraits\AutomateMutators;
use App\ModelTraits\BinHex;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
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
        'uuid', 'landing_url', 'max_cpm', 'max_cpc', 'budget_per_hour', 'time_start', 'time_end', 'require_count'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
    ];

    /**
    * The attributes that use some ModelTraits with mutator settings automation
    *
    * @var array
    */
    protected $traitAutomate = [
        'uuid' => 'BinHex',
    ];
}
