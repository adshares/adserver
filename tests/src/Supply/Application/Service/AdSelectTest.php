<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
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

namespace Adshares\Tests\Supply\Application\Service;

use Adshares\Adserver\Tests\TestCase;
use Adshares\Mock\Client\DummyAdSelectClient;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Service\AdSelect;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdSelectTest extends TestCase
{
    use RefreshDatabase;

    public function testFindBanners(): void
    {
        $this->markTestIncomplete('Create banners for selection');

        $this->app->bind(AdSelect::class, function () {
            return new DummyAdSelectClient();
        });

        $finder = $this->app->make(AdSelect::class);
        $banners = $finder->findBanners([], new ImpressionContext([], [], []));

        self::assertGreaterThan(0, count($banners));
    }
}
