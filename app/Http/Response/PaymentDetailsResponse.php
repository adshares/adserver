<?php declare(strict_types = 1);
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Http\Response;

use Adshares\Adserver\Models\EventLog;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection;

final class PaymentDetailsResponse implements Arrayable
{
    /** @var EventLog[] */
    private $events;

    public function __construct(Collection $events)
    {
        $this->events = $events;
    }

    public function toArray(): array
    {
        return $this->events->map(function (EventLog $entry) {
            $data = $entry->toArray();

            return [
                'event_id' => $data['event_id'],
                'event_type' => $data['event_type'],
                'banner_id' => $data['banner_id'],
                'zone_id' => $data['zone_id'],
                'publisher_id' => $data['publisher_id'],
                'event_value' => $data['paid_amount'],
            ];
        })->all();
    }
}
