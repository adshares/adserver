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

namespace Adshares\Adserver\Tests\Uploader;

use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Uploader\Factory;
use Adshares\Adserver\Uploader\Image\ImageUploader;
use Adshares\Adserver\Uploader\Model\ModelUploader;
use Adshares\Adserver\Uploader\Video\VideoUploader;
use Adshares\Adserver\Uploader\Zip\ZipUploader;
use Adshares\Common\Exception\RuntimeException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

final class FactoryTest extends TestCase
{
    public function testCreateFromRequest(): void
    {
        $request = self::createMock(Request::class);
        $request->expects(self::once())
            ->method('file')
            ->willReturn(UploadedFile::fake()->image('photo.jpg', 300, 250));

        $uploader = Factory::create($request);
        self::assertInstanceOf(ImageUploader::class, $uploader);
    }

    public function testCreateFromRequestNoFile(): void
    {
        $request = self::createMock(Request::class);

        self::expectException(RuntimeException::class);
        Factory::create($request);
    }

    /**
     * @dataProvider typesProvider
     */
    public function testCreateFromType(string $type, string $class): void
    {
        $request = self::createMock(Request::class);

        $uploader = Factory::createFromType($type, $request);
        self::assertInstanceOf($class, $uploader);
    }

    public function typesProvider(): array
    {
        return [
            ['zip', ZipUploader::class],
            ['video', VideoUploader::class],
            ['model', ModelUploader::class],
            ['image', ImageUploader::class],
            ['default', ImageUploader::class],
        ];
    }

    /**
     * @dataProvider fileNameProvider
     */
    public function testCreateFromExtension(string $fileName, string $class): void
    {
        $request = self::createMock(Request::class);

        $uploader = Factory::createFromExtension($fileName, $request);
        self::assertInstanceOf($class, $uploader);
    }

    public function fileNameProvider(): array
    {
        return [
            ['example.zip', ZipUploader::class],
            ['example.mp4', VideoUploader::class],
            ['example.glb', ModelUploader::class],
            ['example.png', ImageUploader::class],
            ['example', ImageUploader::class],
        ];
    }
}
