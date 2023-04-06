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

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Services\Common\AdsTxtCrawler;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\Store\FlockStore;

class SiteAdsTxtCheckCommandTest extends ConsoleTestCase
{
    private const COMMAND_SIGNATURE = 'ops:supply:site-ads-txt:check';

    public function testLock(): void
    {
        Config::updateAdminSettings([Config::ADS_TXT_CRAWLER_ENABLED => '1']);
        $lock = new Lock(new Key(self::COMMAND_SIGNATURE), new FlockStore(), null, false);
        $lock->acquire();

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(Command::FAILURE);
    }

    public function testHandleNewSite(): void
    {
        Config::updateAdminSettings([Config::ADS_TXT_CRAWLER_ENABLED => '1']);
        /** @var Site $siteNotConfirmed */
        $siteNotConfirmed = Site::factory()->create([
            'ads_txt_check_at' => null,
            'ads_txt_confirmed_at' => null,
            'status' => Site::STATUS_PENDING_APPROVAL,
            'user_id' => User::factory()->create(),
        ]);
        $expectedSiteIds = [$siteNotConfirmed->id];
        $mock = $this->createMock(AdsTxtCrawler::class);
        $mock->expects(self::once())
            ->method('checkSites')
            ->with(
                self::callback(
                    fn(Collection $collection) => $collection->map(fn(Site $site) => $site->id)
                        ->diff($expectedSiteIds)
                        ->isEmpty()
                )
            )
            ->willReturn([$siteNotConfirmed->id => true]);
        $this->instance(AdsTxtCrawler::class, $mock);

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(Command::SUCCESS);

        self::assertNotNull($siteNotConfirmed->refresh()->ads_txt_confirmed_at);
        self::assertNotNull($siteNotConfirmed->ads_txt_check_at);
        self::assertEquals(0, $siteNotConfirmed->ads_txt_fails);
    }

    public function testHandleOldSite(): void
    {
        Config::updateAdminSettings([Config::ADS_TXT_CRAWLER_ENABLED => '1']);
        /** @var Site $siteConfirmedYesterday */
        $siteConfirmedYesterday = Site::factory()->create([
            'ads_txt_confirmed_at' => new DateTimeImmutable('-25 hours'),
            'user_id' => User::factory()->create(),
        ]);
        $expectedSiteIds = [$siteConfirmedYesterday->id];
        $mock = $this->createMock(AdsTxtCrawler::class);
        $mock->expects(self::once())
            ->method('checkSites')
            ->with(
                self::callback(
                    fn(Collection $collection) => $collection->map(fn(Site $site) => $site->id)
                        ->diff($expectedSiteIds)
                        ->isEmpty()
                )
            )
            ->willReturn([$siteConfirmedYesterday->id => false]);
        $this->instance(AdsTxtCrawler::class, $mock);

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(Command::SUCCESS);

        self::assertNull($siteConfirmedYesterday->refresh()->ads_txt_confirmed_at);
        self::assertNotNull($siteConfirmedYesterday->ads_txt_check_at);
        self::assertEquals(1, $siteConfirmedYesterday->ads_txt_fails);
        self::assertEquals(Site::STATUS_PENDING_APPROVAL, $siteConfirmedYesterday->status);
    }

    public function testHandleSiteRecentlyConfirmed(): void
    {
        Config::updateAdminSettings([Config::ADS_TXT_CRAWLER_ENABLED => '1']);
        Site::factory()->create([
            'ads_txt_confirmed_at' => new DateTimeImmutable(),
            'user_id' => User::factory()->create(),
        ]);
        $this->app->bind(AdsTxtCrawler::class, function () {
            $mock = $this->createMock(AdsTxtCrawler::class);
            $mock->expects(self::never())->method('checkSites');
            return $mock;
        });

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(Command::SUCCESS);
    }

    public function testHandleWhileAdsTxtDisabled(): void
    {
        /** @var Site $siteNotConfirmed */
        $siteNotConfirmed = Site::factory()->create([
            'ads_txt_confirmed_at' => null,
            'ads_txt_check_at' => null,
            'user_id' => User::factory()->create(),
        ]);
        $this->app->bind(AdsTxtCrawler::class, function () {
            $mock = $this->createMock(AdsTxtCrawler::class);
            $mock->expects(self::never())->method('checkSites');
            return $mock;
        });

        self::artisan(self::COMMAND_SIGNATURE)->assertExitCode(Command::SUCCESS);

        self::assertNull($siteNotConfirmed->refresh()->ads_txt_confirmed_at);
    }
}
