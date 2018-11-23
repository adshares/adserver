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
declare(strict_types = 1);

namespace Adshares\Adserver\Tests\Console;

use Adshares\Adserver\Client\DummyAdClassifyClient;
use Adshares\Adserver\Client\DummyAdUserClient;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Domain\Service\AdClassifyClient;
use Adshares\Common\Domain\Service\AdUserClient;

class UpdateOptionsTest extends TestCase
{
    public function testTargetingOptionsUpdate(): void
    {
        $this->app->bind(AdUserClient::class, function () {
            return new DummyAdUserClient();
        });

        $this->expectExceptionMessage('Method storeTargetingOptions() not implemented');

        $this->artisan('ops:targeting-options:update')
            ->assertExitCode(0);
    }

    public function testFilteringOptionsUpdate(): void
    {
        $this->app->bind(AdClassifyClient::class, function () {
            return new DummyAdClassifyClient();
        });

        $this->expectExceptionMessage('Method storeFilteringOptions() not implemented');

        $this->artisan('ops:filtering-options:update')
            ->assertExitCode(0);
    }

    /** @test */
    public function remember(): void
    {
        $this->markTestIncomplete('Remember to implement Options Storage');
        self::assertTrue(false);
    }

}
