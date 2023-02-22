<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

declare(strict_types=1);

namespace Adshares\Adserver\Tests\Http\Middleware;

use Adshares\Adserver\Http\Middleware\CheckForMaintenanceMode;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Config\AppMode;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CheckForMaintenanceModeTest extends TestCase
{
    public function testHandleException(): void
    {
        config(['app.is_maintenance' => 1]);
        self::assertEquals('maintenance', AppMode::getAppMode());
        $request = new Request(server: ['REQUEST_URI' => 'https://example.com/api/1']);
        $middleware = new CheckForMaintenanceMode($this->app);

        try {
            $middleware->handle($request, fn () => null);
        } catch (HttpException $exception) {
            self::assertEquals(Response::HTTP_SERVICE_UNAVAILABLE, $exception->getStatusCode());
        }

        config(['app.is_maintenance' => 0]);
    }

    public function testHandlePassed(): void
    {
        config(['app.is_maintenance' => 1]);
        $requestIn = new Request(server: ['REQUEST_URI' => 'https://example.com/info.json']);
        $middleware = new CheckForMaintenanceMode($this->app);

        $middleware->handle($requestIn, function ($requestOut) use ($requestIn) {
            self::assertSame($requestIn, $requestOut);
        });

        config(['app.is_maintenance' => 0]);
    }
}
