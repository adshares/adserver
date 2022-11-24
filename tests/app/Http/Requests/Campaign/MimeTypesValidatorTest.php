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

use Adshares\Adserver\Http\Requests\Campaign\MimeTypesValidator;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Mock\Repository\DummyConfigurationRepository;

final class MimeTypesValidatorTest extends TestCase
{
    public function testUnsupportedMimeType(): void
    {
        $banner = new Banner();
        $banner->creative_mime = 'image/bmp';
        $banner->creative_type = Banner::TEXT_TYPE_IMAGE;

        self::expectException(InvalidArgumentException::class);
        self::validator()->validateMimeTypes([$banner]);
    }

    public function testUnsupportedBannerType(): void
    {
        $banner = new Banner();
        $banner->creative_mime = 'image/invalid';
        $banner->creative_type = Banner::TEXT_TYPE_MODEL;

        self::expectException(InvalidArgumentException::class);
        self::validator()->validateMimeTypes([$banner]);
    }

    private static function validator(): MimeTypesValidator
    {
        return new MimeTypesValidator((new DummyConfigurationRepository())->fetchMedium());
    }
}
