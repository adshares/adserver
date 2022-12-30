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

namespace Adshares\Adserver\Tests\Uploader\Video;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\UploadedFile;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Uploader\Video\UploadedVideo;
use Adshares\Adserver\Uploader\Video\VideoUploader;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Mock\Repository\DummyConfigurationRepository;
use Illuminate\Http\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;

final class VideoUploaderTest extends TestCase
{
    public function testUpload(): void
    {
        $uploader = new VideoUploader($this->getRequestMock());
        $medium = (new DummyConfigurationRepository())->fetchMedium();

        $uploadedFile = $uploader->upload($medium);

        self::assertInstanceOf(UploadedVideo::class, $uploadedFile);
        self::assertDatabaseHas(UploadedFile::class, [
            'mime' => 'video/mp4',
            'scope' => '852x480',
        ]);
    }
    public function testUploadFailWhileFileIsMissing(): void
    {
        $uploader = new VideoUploader(new Request());
        $medium = (new DummyConfigurationRepository())->fetchMedium();

        self::expectException(RuntimeException::class);

        $uploader->upload($medium);
    }

    public function testUploadFailWhileSizeTooLarge(): void
    {
        Config::updateAdminSettings([Config::UPLOAD_LIMIT_VIDEO => 0]);
        DatabaseConfigReader::overwriteAdministrationConfig();
        $uploader = new VideoUploader($this->getRequestMock());
        $medium = (new DummyConfigurationRepository())->fetchMedium();

        self::expectException(RuntimeException::class);

        $uploader->upload($medium);
    }

    public function testPreview(): void
    {
        $file = UploadedFile::factory()->create([
            'mime' => 'video/mp4',
        ]);
        $uploader = new VideoUploader(self::createMock(Request::class));

        $response = $uploader->preview(Uuid::fromString($file->uuid));

        self::assertEquals('video/mp4', $response->headers->get('Content-Type'));
    }

    public function testRemoveTemporaryFile(): void
    {
        $file = UploadedFile::factory()->create();
        $uploader = new VideoUploader(self::createMock(Request::class));

        $result = $uploader->removeTemporaryFile(Uuid::fromString($file->uuid));

        self::assertTrue($result);
        self::assertDatabaseMissing(UploadedFile::class, ['id' => $file->id]);
    }

    public function testRemoveTemporaryFileQuietError(): void
    {
        $uploader = new VideoUploader(self::createMock(Request::class));

        $result = $uploader->removeTemporaryFile(Uuid::fromString('971a7dfe-feec-48fc-808a-4c50ccb3a9c6'));

        self::assertFalse($result);
    }

    private function getRequestMock(): Request|MockObject
    {
        Auth::shouldReceive('guard')->andReturnSelf()
            ->shouldReceive('user')->andReturn(User::factory()->create());
        $request = self::createMock(Request::class);
        $request->expects(self::once())
            ->method('file')
            ->willReturn(new File(base_path('tests/mock/Files/Banners/adshares.mp4')));
        return $request;
    }
}
