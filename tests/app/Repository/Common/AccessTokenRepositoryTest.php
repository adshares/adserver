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

declare(strict_types=1);

namespace Adshares\Adserver\Tests\Repository\Common;

use Adshares\Adserver\Repository\Common\AccessTokenRepository;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\ViewModel\AccessToken;
use Laravel\Passport\Bridge\Scope;
use League\OAuth2\Server\Entities\ClientEntityInterface;

final class AccessTokenRepositoryTest extends TestCase
{
    public function testGetNewToken(): void
    {
        $repository = $this->app->make(AccessTokenRepository::class);

        $client = self::createMock(ClientEntityInterface::class);
        $client->method('getIdentifier')->willReturn('test-client');
        $scope = new Scope('campaign.read');

        $token = $repository->getNewToken($client, [$scope]);

        self::assertInstanceOf(AccessToken::class, $token);
    }
}
