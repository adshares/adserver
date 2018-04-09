<?php

namespace App;

use App\ModelTraits\BinHex;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    use BinHex;

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

    public function getUuidAttribute($value)
    {
        return $this->binHexAccessor($value);
    }

    public function setUuidAttribute($value)
    {
        return $this->binHexMutator($value);
    }
}
