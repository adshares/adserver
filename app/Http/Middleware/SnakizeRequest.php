<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TransformsRequest;

class SnakizeRequest extends TransformsRequest
{
    protected function cleanArray(array $data)
    {
        return collect($data)->mapWithKeys(
            function ($value, $key) {
                return $this->cleanValue($key, $value);
            }
        )->all();
    }

    protected function cleanValue($key, $value)
    {
        if (is_array($value)) {
            return $this->transform($key, $this->cleanArray($value));
        }

        return $this->transform($key, $value);
    }

    protected function transform($key, $value)
    {
        return [snake_case($key) => $value];
    }
}
