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
use Adshares\Adserver\Models\Traits\BinHex;
use Adshares\Adserver\Models\Traits\JsonValue;
use Illuminate\Database\Eloquent\Model;

class NetworkPayment extends Model
{
    use AccountAddress;
    use AutomateMutators;
    use BinHex;
    use JsonValue;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
      'receiver_address', 'sender_address', 'sender_host',
      'amount', 'account_hashout', 'account_msid',
      'tx_id', 'tx_time',
      'detailed_data_used',
      'processed',
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
      'receiver_address' => 'AccountAddress',
      'sender_address' => 'AccountAddress',
    ];
}
