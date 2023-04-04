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

namespace Adshares\Adserver\Tests\Uploader\Image;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\UploadedFile;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Uploader\Image\ImageUploader;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Mock\Repository\DummyConfigurationRepository;
use Illuminate\Http\File;
use Illuminate\Http\Request;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;

final class ImageUploaderTest extends TestCase
{
    public function testUploadFailWhileFileIsMissing(): void
    {
        $uploader = new ImageUploader(new Request());
        $medium = (new DummyConfigurationRepository())->fetchMedium();

        self::expectException(RuntimeException::class);

        $uploader->upload($medium);
    }

    public function testUploadFailWhileSizeTooLarge(): void
    {
        Config::updateAdminSettings([Config::UPLOAD_LIMIT_IMAGE => 0]);
        DatabaseConfigReader::overwriteAdministrationConfig();
        $uploader = new ImageUploader($this->getRequestMock());
        $medium = (new DummyConfigurationRepository())->fetchMedium();

        self::expectException(RuntimeException::class);

        $uploader->upload($medium);
    }

    public function testRemoveTemporaryFile(): void
    {
        $file = UploadedFile::factory()->create();
        $uploader = new ImageUploader(self::createMock(Request::class));

        $result = $uploader->removeTemporaryFile(Uuid::fromString($file->uuid));

        self::assertTrue($result);
        self::assertDatabaseMissing(UploadedFile::class, ['id' => $file->id]);
    }

    public function testRemoveTemporaryFileQuietError(): void
    {
        $uploader = new ImageUploader(self::createMock(Request::class));

        $result = $uploader->removeTemporaryFile(Uuid::fromString('971a7dfe-feec-48fc-808a-4c50ccb3a9c6'));

        self::assertFalse($result);
    }

    public function testPreview(): void
    {
        $file = UploadedFile::factory()->create();
        $uploader = new ImageUploader(self::createMock(Request::class));

        $response = $uploader->preview(Uuid::fromString($file->uuid));

        self::assertEquals('image/png', $response->headers->get('Content-Type'));
    }

    private function getRequestMock(): Request|MockObject
    {
        $request = self::createMock(Request::class);
        $request->expects(self::once())
            ->method('file')
            ->willReturn(new File(base_path('tests/mock/Files/Banners/980x120.png')));
        return $request;
    }
}
