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

use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Uploader\Model\ModelUploader;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Mock\Repository\DummyConfigurationRepository;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class ModelUploaderTest extends TestCase
{
    private const DISK = 'banners';

    public function testUpload(): void
    {
        $request = self::createMock(Request::class);
        $request->expects(self::once())
            ->method('file')
            ->willReturn(UploadedFile::fake()->create('a.glb', 1));
        $uploader = new ModelUploader($request);
        $medium = (new DummyConfigurationRepository())->fetchMedium();

        $uploadedFile = $uploader->upload($medium);
        [$name, $extension] = explode('.', $uploadedFile->toArray()['name']);
        self::assertNotEquals('a', $name);
        self::assertEquals('glb', $extension);
    }

    public function testPreviewGltf(): void
    {
        Storage::disk(self::DISK)->put('test_file', 'glTF test content');
        $request = self::createMock(Request::class);
        $uploader = new ModelUploader($request);

        $response = $uploader->preview('test_file');

        self::assertEquals('glTF test content', $response->getContent());
        self::assertEquals('model/gltf-binary', $response->headers->get('Content-Type'));
    }

    public function testPreviewVox(): void
    {
        Storage::disk(self::DISK)->put('test_file', 'VOX test content');
        $request = self::createMock(Request::class);
        $uploader = new ModelUploader($request);

        $response = $uploader->preview('test_file');

        self::assertEquals('VOX test content', $response->getContent());
        self::assertEquals('model/voxel', $response->headers->get('Content-Type'));
    }

    public function testPreviewInvalidFile(): void
    {
        Storage::disk(self::DISK)->put('test_file', 'test content');
        $request = self::createMock(Request::class);
        $uploader = new ModelUploader($request);

        self::expectException(RuntimeException::class);
        $uploader->preview('test_file');
    }

    public function testRemove(): void
    {
        $file = UploadedFile::fake()->create('exists');
        Storage::disk(self::DISK)->put($file->getClientOriginalName(), $file);
        $request = self::createMock(Request::class);
        $uploader = new ModelUploader($request);

        self::assertNull($uploader->removeTemporaryFile('exists'));
    }

    public function testRemoveQuietError(): void
    {
        $request = self::createMock(Request::class);
        $uploader = new ModelUploader($request);

        self::assertNull($uploader->removeTemporaryFile('not_exist'));
    }
}
