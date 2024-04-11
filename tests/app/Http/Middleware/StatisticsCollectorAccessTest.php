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

use Adshares\Adserver\Http\Middleware\StatisticsCollectorAccess;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\AdsAuthenticator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StatisticsCollectorAccessTest extends TestCase
{
    public function testHandleForbidden(): void
    {
        $request = new Request();
        $adsAuthenticatorMock = self::createMock(AdsAuthenticator::class);
        $adsAuthenticatorMock->method('verifyRequest')->willReturn('0001-00000000-9B6F');
        $middleware = new StatisticsCollectorAccess($adsAuthenticatorMock);

        try {
            $middleware->handle($request, fn () => null);
        } catch (HttpException $exception) {
            self::assertEquals(Response::HTTP_FORBIDDEN, $exception->getStatusCode());
        }
    }
}
