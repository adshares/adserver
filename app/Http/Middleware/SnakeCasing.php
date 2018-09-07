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

namespace Adshares\Adserver\Http\Middleware;

use Closure;
use Symfony\Component\HttpFoundation\ParameterBag;

class SnakeCasing
{
    /**
     * The additional attributes passed to the middleware.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     * @param array                    ...$attributes
     *
     * @return mixed
     */
    public function handle($request, Closure $next, ...$attributes)
    {
        $this->attributes = $attributes;

        $this->clean($request);

        return $next($request);
    }

    /**
     * Clean the request's data.
     *
     * @param \Illuminate\Http\Request $request
     */
    protected function clean($request)
    {
        $this->cleanParameterBag($request->query);

        if ($request->isJson()) {
            $this->cleanParameterBag($request->json());
        } else {
            $this->cleanParameterBag($request->request);
        }
    }

    /**
     * Clean the data in the parameter bag.
     *
     * @param \Symfony\Component\HttpFoundation\ParameterBag $bag
     */
    protected function cleanParameterBag(ParameterBag $bag)
    {
        $bag->replace($this->snakeArray($bag->all()));
    }

    /**
     * Clean the data in the given array.
     *
     * @param array $data
     *
     * @return array
     */
    protected function snakeArray(array $data)
    {
        if (empty($data)) {
            return $data;
        }

        $result = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $result[$k] = $this->snakeArray($v);
                continue;
            }
            $result[snake_case($k)] = $v;
        }

        return $result;
    }
}
