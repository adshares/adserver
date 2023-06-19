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

namespace Adshares\Adserver\Tests\Uploader;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Services\Supply\BannerPlaceholderProvider;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Uploader\PlaceholderUploader;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Mock\Repository\DummyConfigurationRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use PDOException;

final class PlaceholderUploaderTest extends TestCase
{
    public function testUpload(): void
    {
        Config::updateAdminSettings([Config::UPLOAD_LIMIT_IMAGE => 0]);
        DatabaseConfigReader::overwriteAdministrationConfig();
        $uploader = new PlaceholderUploader(new BannerPlaceholderProvider());
        $file = UploadedFile::fake()->image('test.png', 300, 250)->size(100);
        $medium = (new DummyConfigurationRepository())->fetchMedium();

        self::expectException(RuntimeException::class);

        $uploader->upload($file, $medium);
    }

    public function testUploadFail(): void
    {
        $provider = self::createMock(BannerPlaceholderProvider::class);
        $provider->expects(self::once())
            ->method('addBannerPlaceholder')
            ->willThrowException(new PDOException('test-exception'));
        $uploader = new PlaceholderUploader($provider);
        $file = UploadedFile::fake()->image('test.png', 300, 250)->size(100);
        $medium = (new DummyConfigurationRepository())->fetchMedium();

        self::expectException(RuntimeException::class);
        Log::shouldReceive('error')
            ->once()
            ->with('Cannot store placeholder: test-exception');

        $uploader->upload($file, $medium);
    }
}
