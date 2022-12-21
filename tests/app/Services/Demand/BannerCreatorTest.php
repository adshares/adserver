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
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\UploadedFile;
use Adshares\Adserver\Services\Demand\BannerCreator;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Exception\InvalidArgumentException;

final class BannerCreatorTest extends TestCase
{
    public function testPrepareBannersFromInputVideo(): void
    {
        $campaign = Campaign::factory()->create();
        $creator = new BannerCreator($this->app->make(ConfigurationRepository::class));
        $file = UploadedFile::factory()->create([
            'mime' => 'video/mp4',
            'scope' => '852x480',
            'content' => file_get_contents(base_path('tests/mock/Files/Banners/adshares.mp4')),
        ]);
        $input = [
            'creative_size' => '852x480',
            'creative_type' => Banner::TEXT_TYPE_VIDEO,
            'name' => 'video 1',
            'url' => 'https://example.com/video/' . $file->ulid,
        ];

        $banners = $creator->prepareBannersFromInput([$input], $campaign);

        self::assertCount(1, $banners);
        self::assertInstanceOf(Banner::class, $banners[0]);
        self::assertEquals('video/mp4', $banners[0]->creative_mime);
        self::assertEquals('852x480', $banners[0]->creative_size);
        self::assertEquals(Banner::TEXT_TYPE_VIDEO, $banners[0]->creative_type);
        self::assertEquals('video 1', $banners[0]->name);
    }

    public function testPrepareBannersFromInputHtml(): void
    {
        $campaign = Campaign::factory()->create();
        $creator = new BannerCreator($this->app->make(ConfigurationRepository::class));
        $file = UploadedFile::factory()->create([
            'mime' => 'text/html',
            'scope' => '300x250',
            'content' => file_get_contents(base_path('tests/mock/Files/Banners/adshares.mp4')),
        ]);
        $input = [
            'creative_size' => '300x250',
            'creative_type' => Banner::TEXT_TYPE_HTML,
            'name' => 'html 1',
            'url' => 'https://example.com/zip/' . $file->ulid,
        ];

        $banners = $creator->prepareBannersFromInput([$input], $campaign);

        self::assertCount(1, $banners);
        self::assertInstanceOf(Banner::class, $banners[0]);
        self::assertEquals('text/html', $banners[0]->creative_mime);
        self::assertEquals('300x250', $banners[0]->creative_size);
        self::assertEquals(Banner::TEXT_TYPE_HTML, $banners[0]->creative_type);
        self::assertEquals('html 1', $banners[0]->name);
    }

    public function testPrepareBannersFromInputDirectLink(): void
    {
        $campaign = Campaign::factory()->create();
        $creator = new BannerCreator($this->app->make(ConfigurationRepository::class));
        $input = [
            'creative_contents' => 'https://example.com/landing',
            'creative_size' => 'pop-up',
            'creative_type' => Banner::TEXT_TYPE_DIRECT_LINK,
            'name' => 'pop-up 1',
        ];

        $banners = $creator->prepareBannersFromInput([$input], $campaign);

        self::assertCount(1, $banners);
        self::assertInstanceOf(Banner::class, $banners[0]);
        self::assertEquals('text/plain', $banners[0]->creative_mime);
        self::assertEquals('pop-up', $banners[0]->creative_size);
        self::assertEquals(Banner::TEXT_TYPE_DIRECT_LINK, $banners[0]->creative_type);
        self::assertEquals('pop-up 1', $banners[0]->name);
    }

    public function testPrepareBannersFromInputFail(): void
    {
        $campaign = Campaign::factory()->create();
        $creator = new BannerCreator($this->app->make(ConfigurationRepository::class));
        self::expectException(InvalidArgumentException::class);

        $creator->prepareBannersFromInput(['name' => 'banner'], $campaign);
    }

    public function testUpdateBanner(): void
    {
        /** @var Banner $banner */
        $banner = Banner::factory()->create(['name' => 'a', 'status' => Banner::STATUS_INACTIVE]);
        $creator = new BannerCreator($this->app->make(ConfigurationRepository::class));

        $creator->updateBanner(['name' => 'b', 'status' => 'active'], $banner);

        self::assertEquals('b', $banner->name);
        self::assertEquals(Banner::STATUS_ACTIVE, $banner->status);
    }

    /**
     * @dataProvider updateBannerFailProvider
     */
    public function testUpdateBannerFail(array $data): void
    {
        /** @var Banner $banner */
        $banner = Banner::factory()->create();
        $creator = new BannerCreator($this->app->make(ConfigurationRepository::class));
        self::expectException(InvalidArgumentException::class);

        $creator->updateBanner($data, $banner);
    }

    public function updateBannerFailProvider(): array
    {
        return [
            'invalid status type' => [['name' => 'b', 'status' => [Banner::STATUS_INACTIVE]]],
            'invalid status value' => [['name' => 'b', 'status' => 'invalid']],
        ];
    }
}
