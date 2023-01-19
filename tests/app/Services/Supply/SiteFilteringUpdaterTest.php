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

namespace Adshares\Adserver\Tests\Services\Supply;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Services\Supply\SiteFilteringUpdater;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\DatabaseConfigReader;

class SiteFilteringUpdaterTest extends TestCase
{
    public function testAddClassificationToFilteringImmutability(): void
    {
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => User::factory()->create()]);

        (new SiteFilteringUpdater())->addClassificationToFiltering($site);
        $r1 = $site->site_requires;
        $e1 = $site->site_excludes;

        (new SiteFilteringUpdater())->addClassificationToFiltering($site);
        $r2 = $site->site_requires;
        $e2 = $site->site_excludes;

        self::assertEquals($r1, $r2);
        self::assertEquals($e1, $e2);
    }

    public function testAddClassificationToFilteringInitialInternalRequirements(): void
    {
        /** @var Site $site */
        $site = Site::factory()->make(['user_id' => User::factory()->create()]);
        $site->site_requires = ['classify' => ['0:1']];
        $site->site_excludes = ['classify' => ['0:0']];
        $site->save();

        (new SiteFilteringUpdater())->addClassificationToFiltering($site);

        self::assertArrayNotHasKey('classify:classified', $site->site_requires);
        self::assertContains('0:1', $site->site_requires['classify']);
        self::assertContains('0:0', $site->site_excludes['classify']);
    }

    public function testAddClassificationToFilteringSwitchOnlyAcceptedBanners(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Site $site */
        $site = Site::factory()->create(['only_accepted_banners' => true, 'user_id' => $user]);
        $expectedKey = sprintf('%d:%d:1', $user->id, $site->id);

        (new SiteFilteringUpdater())->addClassificationToFiltering($site);

        self::assertArrayHasKey('classify', $site->site_requires);
        self::assertContains($expectedKey, $site->site_requires['classify']);

        $site->only_accepted_banners = false;
        $site->save();

        (new SiteFilteringUpdater())->addClassificationToFiltering($site);

        self::assertArrayNotHasKey('classify', $site->site_requires);
    }

    public function testAddClassificationToFilteringGlobalFiltering(): void
    {
        Config::updateAdminSettings([
            Config::SITE_FILTERING_EXCLUDE => '{"000100000024ff89:category": ["adult", "annoying", "malware"]}',
            Config::SITE_FILTERING_REQUIRE =>
                '{"000100000024ff89:quality": ["high"], "000100000024ff89:classified": ["1"]}',
        ]);
        DatabaseConfigReader::overwriteAdministrationConfig();
        $user = User::factory()->create();
        /** @var Site $site */
        $site = Site::factory()->create(['user_id' => $user]);

        (new SiteFilteringUpdater())->addClassificationToFiltering($site);

        self::assertEquals(['adult', 'annoying', 'malware'], $site->site_excludes['000100000024ff89:category']);
        self::assertEquals(['high'], $site->site_requires['000100000024ff89:quality']);
        self::assertEquals(['1'], $site->site_requires['000100000024ff89:classified']);
    }

    public function testAddClassificationToFilteringAppendClassified(): void
    {
        $user = User::factory()->create();
        /** @var Site $site */
        $site = Site::factory()->create([
            'site_requires' => ['000100000024ff89:quality' => ['high']],
            'user_id' => $user,
        ]);

        (new SiteFilteringUpdater())->addClassificationToFiltering($site);

        self::assertEquals(['high'], $site->site_requires['000100000024ff89:quality']);
        self::assertEquals(['1'], $site->site_requires['000100000024ff89:classified']);
    }
}
