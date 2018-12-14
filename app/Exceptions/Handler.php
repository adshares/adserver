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

namespace Adshares\Adserver\Exceptions;

use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Handler extends ExceptionHandler
{
    private const ENV_DEV = 'dev';

    public function render($request, Exception $exception)
    {
        $env = config('app.env');

        if ($exception instanceof HttpException) {
            return $this->response($exception->getMessage(), $exception->getStatusCode(), $exception->getTrace());
        }

        if ($exception instanceof QueryException) {
            if ($prev = $exception->getPrevious()) {
                return $this->response($exception->getMessage(), $exception->getStatusCode(), $exception->getTrace());
            }

            return $this->response(
                $exception->getMessage(),
                $exception->getStatusCode(),
                $exception->getTrace(),
                $exception->getSql()
            );
        }

        return $this->response(
            $env === self::ENV_DEV ? $exception->getMessage() : 'Internal error.',
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $exception->getTrace()
        );
    }

    private function response(string $message, int $code, array $trace, ?string $detail = ''): JsonResponse
    {
        $data = [
            'code' => $code,
            'message' => $message,

        ];

        if (config('app.env') === self::ENV_DEV) {
            $data['trace'] = $trace;
        }

        if ($detail) {
            $data['detail'] = $detail;
        }

        return new JsonResponse($data);
    }
}
