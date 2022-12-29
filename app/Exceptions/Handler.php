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

namespace Adshares\Adserver\Exceptions;

use Adshares\Adserver\Http\Utils;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Laravel\Passport\Exceptions\OAuthServerException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

use function array_filter;
use function json_encode;
use function sprintf;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $exception): Response
    {
        if ($exception instanceof HttpException) {
            return $this->response(
                $exception->getMessage(),
                $exception->getStatusCode(),
                $exception->getTrace()
            );
        }

        if ($exception instanceof QueryException) {
            if ($prev = $exception->getPrevious()) {
                return $this->response(
                    $prev->getMessage(),
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                    $prev->getTrace(),
                );
            }

            return $this->response(
                $exception->getMessage(),
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $exception->getTrace(),
                [],
                $exception->getSql()
            );
        }

        if ($exception instanceof ModelNotFoundException) {
            return $this->response(
                $exception->getMessage(),
                Response::HTTP_NOT_FOUND,
                $exception->getTrace()
            );
        }

        if ($exception instanceof ValidationException || $exception instanceof OAuthServerException) {
            return $this->response(
                $exception->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $exception->getTrace(),
                $exception instanceof ValidationException ? $exception->errors() : [],
            );
        }

        if ($exception instanceof AuthenticationException) {
            return $this->shouldReturnJson($request, $exception)
                ? $this->response($exception->getMessage(), Response::HTTP_UNAUTHORIZED, $exception->getTrace())
                : redirect()->guest($exception->redirectTo() ?? $this->getAdPanelLoginUrl($request));
        }
        if ($exception instanceof InvalidArgumentException) {
            return $this->response(
                $exception->getMessage(),
                Response::HTTP_BAD_REQUEST,
                $exception->getTrace()
            );
        }

        if ($exception instanceof AuthorizationException) {
            return $this->response(
                $exception->getMessage(),
                $exception->hasStatus() ? $exception->status() : Response::HTTP_FORBIDDEN,
                $exception->getTrace(),
            );
        }

        return $this->response(
            $exception->getMessage(),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $exception->getTrace()
        );
    }

    private function response(
        string $message,
        int $code,
        array $trace,
        array $errors = [],
        ?string $detail = ''
    ): JsonResponse {
        $data = [
            'code' => $code,
            'message' => (config('app.env') === Utils::ENV_DEV || $code < 500) ? $message : 'Internal error',
        ];

        if (!empty($errors)) {
            $data['errors'] = $errors;
        }

        if (config('app.env') === Utils::ENV_DEV) {
            $data['trace'] = $trace;

            if ($detail) {
                $data['detail'] = $detail;
            }
        }

        Log::debug(sprintf('%s #%s %s %s', $message, $code, $detail, json_encode($trace)));

        return new JsonResponse($data, $code);
    }

    public function report(Throwable $e)
    {
        if ($this->shouldntReport($e)) {
            return;
        }

        if (method_exists($e, 'report')) {
            $e->report();
            return;
        }

        try {
            $logger = $this->container->make(LoggerInterface::class);
        } catch (Exception $ex) {
            throw $e;
        }

        $logger->error(
            sprintf(
                '{"file":"%s:%s","message":%s,"context":%s,"trace":%s}',
                $e->getFile(),
                $e->getLine(),
                str_replace(["\n", "\r"], ' ', json_encode($e->getMessage())),
                str_replace(["\n", "\r"], ' ', json_encode($this->context())),
                str_replace(
                    ["\n", "\r"],
                    ' ',
                    json_encode(
                        array_filter(
                            $e->getTrace(),
                            function (array $row) {
                                return stripos($row['file'] ?? '', 'vendor') === false;
                            }
                        )
                    )
                )
            )
        );
    }

    private function getAdPanelLoginUrl(Request $request): string
    {
        return config('app.adpanel_url') . '/auth/login?redirect_uri=' . urlencode($request->fullUrl());
    }
}
