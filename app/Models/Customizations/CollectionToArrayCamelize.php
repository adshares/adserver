<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer.  If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Models\Customizations;

use Illuminate\Database\Eloquent\Collection;

/**
 * Fix Hash key camelized return from collection.
 */
class CollectionToArrayCamelize extends Collection
{
    /**
     * Get the collection of items as a plain array with key camel fixed.
     *
     * @return array
     */
    public function toArrayCamelize()
    {
        return array_map(function ($value) {
            return $value->toArrayCamelize();
        }, $this->items);
    }
}
