<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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
use Symfony\Component\HttpFoundation\Response;

class CamelizeResponse
{
    public function handle($request, Closure $next)
    {
        return $this->camelizeJsonResponse($next($request));
    }

    private function camelizeJsonResponse(Response $response)
    {
        if ($response instanceof JsonResponse) {
            $response->setContent($this->camelizeJsonKeys($response->content()));
        }

        return $response;
    }

    private function camelizeJsonKeys(string $json): string
    {
        return preg_replace_callback('/"([^"]+?)"\s*:/', function (array $input): string {
            return '"' . camel_case($input[1]) . '":';
        }, $json);
    }
}
