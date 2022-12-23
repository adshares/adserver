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

namespace Adshares\Adserver\Tests\Uploader\Zip;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\UploadedFile as UploadedFileModel;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Uploader\Zip\UploadedZip;
use Adshares\Adserver\Uploader\Zip\ZipUploader;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Mock\Repository\DummyConfigurationRepository;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\MockObject\MockObject;

final class ZipUploaderTest extends TestCase
{
    public function testUpload(): void
    {
        $uploader = new ZipUploader($this->getRequestMock());
        $medium = (new DummyConfigurationRepository())->fetchMedium();

        $uploaded = $uploader->upload($medium);

        self::assertInstanceOf(UploadedZip::class, $uploaded);
        self::assertDatabaseHas(UploadedFileModel::class, [
            'mime' => 'text/html',
            'scope' => null,
        ]);
    }

    public function testUploadEmpty(): void
    {
        $request = self::createMock(Request::class);
        $request->expects(self::once())
            ->method('file')
            ->willReturn(
                UploadedFile::fake()->createWithContent(
                    'a.zip',
                    file_get_contents(base_path('tests/mock/Files/Banners/empty.zip'))
                )
            );
        $medium = (new DummyConfigurationRepository())->fetchMedium();

        self::expectException(RuntimeException::class);

        (new ZipUploader($request))->upload($medium);
    }

    public function testUploadFailWhileSizeTooLarge(): void
    {
        Config::updateAdminSettings([Config::UPLOAD_LIMIT_ZIP => 0]);
        DatabaseConfigReader::overwriteAdministrationConfig();
        $uploader = new ZipUploader($this->getRequestMock());
        $medium = (new DummyConfigurationRepository())->fetchMedium();

        self::expectException(RuntimeException::class);

        $uploader->upload($medium);
    }

    public function testRemoveTemporaryFile(): void
    {
        $file = UploadedFileModel::factory()->create();
        $uploader = new ZipUploader(self::createMock(Request::class));

        $uploader->removeTemporaryFile($file->ulid);

        self::assertDatabaseMissing(UploadedFileModel::class, ['id' => $file->id]);
    }

    public function testRemoveTemporaryFileQuietError(): void
    {
        $uploader = new ZipUploader(self::createMock(Request::class));

        self::expectNotToPerformAssertions();

        $uploader->removeTemporaryFile('01gmt6dvqqm5h4d908hwrh82jh');
    }

    public function testPreview(): void
    {
        $file = UploadedFileModel::factory()->create([
            'mime' => 'text/html',
            'content' => 'html content',
        ]);
        $uploader = new ZipUploader(self::createMock(Request::class));

        $response = $uploader->preview($file->ulid);

        self::assertEquals(200, $response->getStatusCode());
        self::assertEquals('html content', $response->getContent());
    }

    private function getRequestMock(): Request|MockObject
    {
        $request = self::createMock(Request::class);
        $request->expects(self::once())
            ->method('file')
            ->willReturn(UploadedFile::fake()->createWithContent(
                'a.zip',
                file_get_contents(base_path('tests/mock/Files/Banners/300x250.zip'))
            ));
        return $request;
    }
}
