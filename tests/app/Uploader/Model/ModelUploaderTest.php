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

namespace Adshares\Adserver\Tests\Uploader\Model;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\UploadedFile;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Uploader\Model\ModelUploader;
use Adshares\Adserver\Uploader\Model\UploadedModel;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Mock\Repository\DummyConfigurationRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\File;
use Illuminate\Http\Request;
use PHPUnit\Framework\MockObject\MockObject;

final class ModelUploaderTest extends TestCase
{
    public function testUpload(): void
    {
        $uploader = new ModelUploader($this->getRequestMock());
        $medium = (new DummyConfigurationRepository())->fetchMedium('metaverse', 'decentraland');

        $uploadedFile = $uploader->upload($medium);

        self::assertInstanceOf(UploadedModel::class, $uploadedFile);
    }

    public function testUploadFailWhileSizeTooLarge(): void
    {
        Config::updateAdminSettings([Config::UPLOAD_LIMIT_MODEL => 0]);
        DatabaseConfigReader::overwriteAdministrationConfig();
        $uploader = new ModelUploader($this->getRequestMock());
        $medium = (new DummyConfigurationRepository())->fetchMedium('metaverse', 'decentraland');

        self::expectException(RuntimeException::class);

        $uploader->upload($medium);
    }

    public function testPreviewGltf(): void
    {
        $file = UploadedFile::factory()->create([
            'mime' => 'model/gltf-binary',
            'content' => 'glTF test content',
        ]);
        $uploader = new ModelUploader(self::createMock(Request::class));

        $response = $uploader->preview($file->ulid);

        self::assertEquals('glTF test content', $response->getContent());
        self::assertEquals('model/gltf-binary', $response->headers->get('Content-Type'));
    }

    public function testPreviewVox(): void
    {
        $file = UploadedFile::factory()->create([
            'mime' => 'model/voxel',
            'content' => 'VOX test content',
        ]);
        $uploader = new ModelUploader(self::createMock(Request::class));

        $response = $uploader->preview($file->ulid);

        self::assertEquals('VOX test content', $response->getContent());
        self::assertEquals('model/voxel', $response->headers->get('Content-Type'));
    }

    public function testPreviewInvalidFile(): void
    {
        $uploader = new ModelUploader(self::createMock(Request::class));

        self::expectException(ModelNotFoundException::class);
        $uploader->preview('01gmt6dvqqm5h4d908hwrh82jh');
    }

    public function testRemoveTemporaryFile(): void
    {
        $file = UploadedFile::factory()->create();
        $uploader = new ModelUploader(self::createMock(Request::class));

        $uploader->removeTemporaryFile($file->ulid);

        self::assertDatabaseMissing(UploadedFile::class, ['id' => $file->id]);
    }

    public function testRemoveTemporaryFileQuietError(): void
    {
        $uploader = new ModelUploader(self::createMock(Request::class));

        self::expectNotToPerformAssertions();

        $uploader->removeTemporaryFile('01gmt6dvqqm5h4d908hwrh82jh');
    }

    private function getRequestMock(): Request|MockObject
    {
        $request = self::createMock(Request::class);
        $request->expects(self::once())
            ->method('file')
            ->willReturn(new File(base_path('tests/mock/Files/Banners/model.vox')));
        return $request;
    }
}
