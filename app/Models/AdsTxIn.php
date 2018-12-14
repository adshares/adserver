<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
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
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Models;

use Illuminate\Database\Eloquent\Model;

class AdsTxIn extends Model
{

    public const STATUS_NEW = 0;

    public const STATUS_USER_DEPOSIT = 1;

    public const STATUS_EVENT_PAYMENT = 2;

    public const STATUS_RESERVED = 64;

    /**
     * Invalid tx
     */
    public const STATUS_INVALID = -1;

    public $incrementing = false;

    protected $table = 'ads_tx_in';

    protected $primaryKey = 'txid';

    protected $keyType = 'string';
}
