<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer.  If not, see <https://www.gnu.org/licenses/>
 */

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
