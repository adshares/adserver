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

namespace Adshares\Adserver\Tests\Http\Requests\Campaign;

use Adshares\Adserver\Http\Requests\Campaign\BannerValidator;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Mock\Repository\DummyConfigurationRepository;

final class BannerValidatorTest extends TestCase
{
    /**
     * @dataProvider validBannerProvider
     */
    public function testValid(array $banner): void
    {
        self::expectNotToPerformAssertions();

        $this->bannerValidator()->validateBanner($banner);
    }

    public function validBannerProvider(): array
    {
        return [
            'direct no url' => [
                [
                    'contents' => 'https://example.com/landing',
                    'name' => 'test',
                    'scope' => 'pop-up',
                    'type' => Banner::TEXT_TYPE_DIRECT_LINK,
                ]
            ],
        ];
    }
    /**
     * @dataProvider invalidBannerProvider
     */
    public function testInvalid(array $banner): void
    {
        self::expectException(InvalidArgumentException::class);

        $this->bannerValidator()->validateBanner($banner);
    }

    public function invalidBannerProvider(): array
    {
        return [
            'invalid image size no match' => [
                [
                    'name' => 'test',
                    'scope' => '1x1',
                    'type' => Banner::TEXT_TYPE_IMAGE,
                    'url' => 'https://example.com/file.png',
                ]
            ],
            'invalid type for web medium' => [
                [
                    'name' => 'test',
                    'scope' => 'cube',
                    'type' => Banner::TEXT_TYPE_MODEL,
                    'url' => 'https://example.com/file.glb',
                ]
            ],
            'invalid video size format' => [
                [
                    'name' => 'test',
                    'scope' => 'mp4',
                    'type' => Banner::TEXT_TYPE_VIDEO,
                    'url' => 'https://example.com/file.mp4',
                ]
            ],
            'invalid video size no match' => [
                [
                    'name' => 'test',
                    'scope' => '1x1',
                    'type' => Banner::TEXT_TYPE_VIDEO,
                    'url' => 'https://example.com/file.mp4',
                ]
            ],
        ];
    }

    public function testValidateBannerMetaDataFail(): void
    {
        $banner = [
            'file_id' => '1',
            'name' => 'example',
        ];
        self::expectException(InvalidArgumentException::class);

        $this->bannerValidator()->validateBannerMetaData($banner);
    }

    private function bannerValidator(): BannerValidator
    {
        return new BannerValidator((new DummyConfigurationRepository())->fetchMedium());
    }

    public function testUnknownMimeType(): void
    {
        self::expectException(InvalidArgumentException::class);

        self::bannerValidator()->validateMimeType(Banner::TEXT_TYPE_IMAGE, null);
    }

    public function testUnsupportedMimeType(): void
    {
        self::expectException(InvalidArgumentException::class);

        self::bannerValidator()->validateMimeType(Banner::TEXT_TYPE_IMAGE, 'image/bmp');
    }

    public function testUnsupportedBannerType(): void
    {
        self::expectException(InvalidArgumentException::class);

        self::bannerValidator()->validateMimeType(Banner::TEXT_TYPE_MODEL, 'image/invalid');
    }
}
