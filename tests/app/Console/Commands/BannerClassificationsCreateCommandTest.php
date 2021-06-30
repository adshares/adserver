<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\BannerClassification;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Illuminate\Database\Eloquent\Collection;

use function implode;

class BannerClassificationsCreateCommandTest extends ConsoleTestCase
{
    private const CLASSIFIER_NAME = 'test_classifier';

    public function testCreateBannerClassifications(): void
    {
        $bannerCount = 3;

        $bannerCollection = new Collection();
        for ($i = 0; $i < $bannerCount; $i++) {
            $bannerCollection->add($this->insertBanner());
        }

        $this->artisan('ops:demand:classification:create', ['classifier' => self::CLASSIFIER_NAME])->assertExitCode(0);

        $bannerClassificationCollection = BannerClassification::all();

        $this->assertEquals($bannerCount, $bannerClassificationCollection->count());
        $bannerClassificationCollection->each(
            function ($item) {
                /** @var BannerClassification $item */
                $this->assertEquals(BannerClassification::STATUS_NEW, $item->status);
                $this->assertEquals(self::CLASSIFIER_NAME, $item->classifier);
                $this->assertNull($item->keywords);
            }
        );
    }

    public function testCreateBannerClassificationsPassBannerIds(): void
    {
        $bannerCount = 3;

        $bannerCollection = new Collection();
        for ($i = 0; $i < $bannerCount; $i++) {
            $bannerCollection->add($this->insertBanner());
        }

        $bannerIds = $bannerCollection->pluck('id')->toArray();

        $this->artisan(
            'ops:demand:classification:create',
            [
                'classifier' => self::CLASSIFIER_NAME,
                '--bannerIds' => implode(',', $bannerIds),
            ]
        )->assertExitCode(0);

        $bannerClassificationCollection = BannerClassification::all();

        $this->assertEquals($bannerCount, $bannerClassificationCollection->count());
        $bannerClassificationCollection->each(
            function ($item) {
                /** @var BannerClassification $item */
                $this->assertEquals(BannerClassification::STATUS_NEW, $item->status);
                $this->assertEquals(self::CLASSIFIER_NAME, $item->classifier);
                $this->assertNull($item->keywords);
            }
        );
    }

    private function insertBanner(): Banner
    {
        $user = factory(User::class)->create();
        $campaign = factory(Campaign::class)->create(['status' => Campaign::STATUS_ACTIVE, 'user_id' => $user->id]);

        return factory(Banner::class)->create(['campaign_id' => $campaign->id, 'status' => Banner::STATUS_ACTIVE]);
    }
}
