<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CamelizeJsonResponse
{
    private const FORBIDDEN_KEYS = [
        'filtering',
        'targeting',
    ];

    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            return $this->camelizeJsonResponse($response);
        }

        return $response;
    }

    private function camelizeJsonResponse(JsonResponse $response): JsonResponse
    {
        $content = $response->getData(true);

        if (is_array($content)) {
            $json = $this->camelizeJsonKeys($content);
            $response->setData($json);
        }

        return $response;
    }

    private function camelizeJsonKeys(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (in_array($key, self::FORBIDDEN_KEYS, true)) {
                $result[$key] = $value;
            } else {
                $result[Str::camel($key)] = is_array($value) ? $this->camelizeJsonKeys($value) : $value;
            }
        }
        return $result;
    }
}
