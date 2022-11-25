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

namespace Adshares\Adserver\Tests\Services\Demand;

use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Services\Demand\BannerCreator;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Exception\InvalidArgumentException;

final class BannerCreatorTest extends TestCase
{
    public function testUpdateBanner(): void
    {
        /** @var Banner $banner */
        $banner = Banner::factory()->create(['name' => 'a', 'status' => Banner::STATUS_INACTIVE]);
        $creator = new BannerCreator($this->app->make(ConfigurationRepository::class));

        $creator->updateBanner(['name' => 'b', 'status' => Banner::STATUS_ACTIVE], $banner);

        self::assertEquals('b', $banner->name);
        self::assertEquals(Banner::STATUS_ACTIVE, $banner->status);
    }

    public function testUpdateBannerFail(): void
    {
        /** @var Banner $banner */
        $banner = Banner::factory()->create();
        $creator = new BannerCreator($this->app->make(ConfigurationRepository::class));
        self::expectException(InvalidArgumentException::class);

        $creator->updateBanner(['name' => 'b', 'status' => 'active'], $banner);
    }
}
