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

use Adshares\Adserver\Services\Supply\BannerPlaceholderConverter;
use Adshares\Adserver\Services\Supply\BannerPlaceholderProvider;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\UuidStringGenerator;
use Adshares\Common\Application\Dto\TaxonomyV2\Format;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Application\Dto\TaxonomyV2\Targeting;
use Adshares\Common\Domain\Adapter\ArrayableItemCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class BannerPlaceholderConverterTest extends TestCase
{
    public function testConvertToImagesWhileDefault(): void
    {
        $provider = $this->createMock(BannerPlaceholderProvider::class);
        $provider->expects(self::never())->method('addBannerPlaceholder');
        $provider->expects(self::once())->method('addDefaultBannerPlaceholder');
        $converter = new BannerPlaceholderConverter($provider);
        $targeting = new Targeting(
            new ArrayableItemCollection(),
            new ArrayableItemCollection(),
            new ArrayableItemCollection(),
        );
        $formats = new ArrayableItemCollection();
        $formats->add(new Format('image', ['image/png', 'image/jpeg', 'image/invalid'], ['1x1' => '1']));
        $medium = new Medium('test', 'test', null, null, $formats, $targeting);

        Log::shouldReceive('error')
            ->once()
            ->with('Cannot convert to mime image/invalid');

        $converter->convertToImages(
            UploadedFile::fake()->image('seed.png', 1, 1),
            $medium,
            '1x1',
            UuidStringGenerator::v4(),
            true,
        );
    }

    public function testConvertToHtmlWhileDefault(): void
    {
        $provider = $this->createMock(BannerPlaceholderProvider::class);
        $provider->expects(self::never())->method('addBannerPlaceholder');
        $provider->expects(self::once())->method('addDefaultBannerPlaceholder');
        $converter = new BannerPlaceholderConverter($provider);
        $targeting = new Targeting(
            new ArrayableItemCollection(),
            new ArrayableItemCollection(),
            new ArrayableItemCollection(),
        );
        $formats = new ArrayableItemCollection();
        $formats->add(new Format('html', ['text/html'], ['1x1' => '1']));
        $medium = new Medium('test', 'test', null, null, $formats, $targeting);

        $converter->convertToHtml(
            UploadedFile::fake()->image('seed.png', 1, 1),
            $medium,
            '1x1',
            UuidStringGenerator::v4(),
            true,
        );
    }
}
