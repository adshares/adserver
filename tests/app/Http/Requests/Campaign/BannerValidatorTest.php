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
                    'size' => 'pop-up',
                    'type' => Banner::TEXT_TYPE_DIRECT_LINK,
                ]
            ],
        ];
    }

    private function bannerValidator(): BannerValidator
    {
        return new BannerValidator((new DummyConfigurationRepository())->fetchMedium());
    }
}
