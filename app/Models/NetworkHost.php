<?php

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Models\Traits\AccountAddress;
use Adshares\Adserver\Models\Traits\AutomateMutators;

use Illuminate\Database\Eloquent\Model;

class NetworkHost extends Model
{
    use AccountAddress;
    use AutomateMutators;

    protected $primaryKey = 'address';
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
      'address', 'host', 'last_seen', 'banner_id',
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
      'address' => 'AccountAddress',
    ];

    public static function registerHost($address, $host)
    {
        $h = self::find(hex2bin(self::decodeAddress($address)));
        if (empty($h)) {
            $h = new self();
            $h->address = $address;
            $h->host = $host;
        }
        $h->last_seen = time();
        $h->save();
        return $h;
    }
}
