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
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SnakeCasing
{
    protected $attributes = [];

    public function handle($request, Closure $next)
    {
        return $this->camelize($next($this->snakize($request)));
    }

    private function snakize(Request $request): Request
    {
        $this->snakizeParameterBag($request->query);

        if ($request->isJson()) {
            $this->snakizeParameterBag($request->json());
        } else {
            $this->snakizeParameterBag($request->request);
        }

        return $request;
    }

    private function camelize(Response $response): Response
    {
        if ($response instanceof JsonResponse) {
            return $response->setContent(
                preg_replace_callback(
                    '/"([^"]+?)"\s*:/',
                    function (array $input): string {
                        return '"' . camel_case($input[1]) . '":';
                    },
                    $response->content()
                )
            );
        }



        return $response;
    }

    private function snakizeParameterBag(ParameterBag $bag): void
    {
        $bag->replace($this->transformArrayKeys($bag->all(), 'snake_case'));
    }

    private function transformArrayKeys(array $data, string $transformation): array
    {
        $result = [];

        if (empty($data)) {
            return $result;
        }

        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $result[$k] = $this->transformArrayKeys($v);
                continue;
            }
            $result[$transformation($k)] = $v;
        }

        return $result;
    }

}
