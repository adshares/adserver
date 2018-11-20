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

declare(strict_types = 1);

namespace Adshares\Adserver\Models;

use Adshares\Adserver\Models\Traits\AutomateMutators;
use Illuminate\Database\Eloquent\Model;
use DateTime;

/**
 * @property int id
 * @property string address
 * @property string host
 * @property int created_at
 * @property int updated_at
 * @property int deleted_at
 * @property int last_broadcast
 */
class NetworkHost extends Model
{
    use AutomateMutators;

    /**
     * @var array
     */
    protected $fillable = [
        'address',
        'host',
        'last_broadcast',
    ];

    public static function registerHost(string $address, string $host, ?\DateTime $lastBroadcast = null): NetworkHost
    {
        $networkHost = self::where('address', $address)->first();

        if (empty($networkHost)) {
            $networkHost = new self();
            $networkHost->address = $address;
        }

        $networkHost->host = $host;
        $networkHost->last_broadcast = (null === $lastBroadcast) ? new DateTime() : $lastBroadcast;
        $networkHost->save();

        return $networkHost;
    }

    public function updateHost(): void
    {
        $this->last_broadcast = new \DateTime();
        $this->update();
    }
}
