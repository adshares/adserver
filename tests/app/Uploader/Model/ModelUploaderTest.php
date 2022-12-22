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

use Adshares\Adserver\Models\UploadedFile;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Uploader\Model\ModelUploader;
use Adshares\Adserver\Uploader\Model\UploadedModel;
use Adshares\Mock\Repository\DummyConfigurationRepository;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class ModelUploaderTest extends TestCase
{
    private const DISK = 'banners';

    public function testUpload(): void
    {
        $request = self::createMock(Request::class);
        $request->expects(self::once())
            ->method('file')
            ->willReturn(new File(base_path('tests/mock/Files/Banners/model.vox')));
        $uploader = new ModelUploader($request);
        $medium = (new DummyConfigurationRepository())->fetchMedium('metaverse', 'decentraland');

        $uploadedFile = $uploader->upload($medium);

        self::assertInstanceOf(UploadedModel::class, $uploadedFile);
    }

    public function testPreviewGltf(): void
    {
        $file = UploadedFile::factory()->create([
            'mime' => 'model/gltf-binary',
            'content' => 'glTF test content',
        ]);
        $request = self::createMock(Request::class);
        $uploader = new ModelUploader($request);

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
        Storage::disk(self::DISK)->put('test_file', 'VOX test content');
        $request = self::createMock(Request::class);
        $uploader = new ModelUploader($request);

        $response = $uploader->preview($file->ulid);

        self::assertEquals('VOX test content', $response->getContent());
        self::assertEquals('model/voxel', $response->headers->get('Content-Type'));
    }

    public function testPreviewInvalidFile(): void
    {
        $request = self::createMock(Request::class);
        $uploader = new ModelUploader($request);

        self::expectException(ModelNotFoundException::class);
        $uploader->preview('01gmt6dvqqm5h4d908hwrh82jh');
    }

    public function testRemove(): void
    {
        $file = UploadedFile::factory()->create();
        $request = self::createMock(Request::class);
        $uploader = new ModelUploader($request);

        $uploader->removeTemporaryFile($file->ulid);

        self::assertDatabaseMissing(UploadedFile::class, ['id' => $file->id]);
    }

    public function testRemoveQuietError(): void
    {
        $request = self::createMock(Request::class);
        $uploader = new ModelUploader($request);

        self::expectNotToPerformAssertions();

        $uploader->removeTemporaryFile('01gmt6dvqqm5h4d908hwrh82jh');
    }

    public function testContentWhenFileMissing(): void
    {
        self::expectException(FileNotFoundException::class);

        ModelUploader::content('a.glb');
    }
}
