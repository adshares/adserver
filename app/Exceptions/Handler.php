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

use Adshares\Adserver\Http\Utils;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use function GuzzleHttp\json_encode;

class Handler extends ExceptionHandler
{
    public function render($request, Exception $exception)
    {
        if ($exception instanceof HttpException) {
            return $this->response($exception->getMessage(), $exception->getStatusCode(), $exception->getTrace());
        }

        if ($exception instanceof QueryException) {
            if ($prev = $exception->getPrevious()) {
                return $this->response(
                    $exception->getMessage(),
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                    $exception->getTrace()
                );
            }

            return $this->response(
                $exception->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $exception->getTrace(),
                $exception->getSql()
            );
        }

        if ($exception instanceof ModelNotFoundException) {
            return $this->response($exception->getMessage(), Response::HTTP_NOT_FOUND, $exception->getTrace());
        }

        if ($exception instanceof ValidationException) {
            return $this->response(
                $exception->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $exception->getTrace()
            );
        }

        return $this->response(
            $exception->getMessage(),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $exception->getTrace()
        );
    }

    public function report(Exception $e)
    {
        if ($this->shouldntReport($e)) {
            return;
        }

        if (method_exists($e, 'report')) {
            return $e->report();
        }

        try {
            $logger = $this->container->make(LoggerInterface::class);
        } catch (Exception $ex) {
            throw $e;
        }

        $logger->error(
            sprintf(
                '{"message":"%s","context":%s,"trace":%s,"file":"%s:%s"}',
                $e->getMessage(),
                json_encode($this->context()),
                json_encode($e->getTrace()),
                $e->getFile(),
                $e->getLine()
            )
        );
    }

    private function response(string $message, int $code, array $trace, ?string $detail = ''): JsonResponse
    {
        $data = [
            'code' => $code,
            'message' => config('app.env') === Utils::ENV_DEV ? $message : 'Internal error.',

        ];

        if (config('app.env') === Utils::ENV_DEV) {
            $data['trace'] = $trace;

            if ($detail) {
                $data['detail'] = $detail;
            }
        }

        return new JsonResponse($data, $code);
    }
}
