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
use Adshares\Adserver\Uploader\DirectLink\DirectLinkUploader;
use Adshares\Adserver\Uploader\Factory;
use Adshares\Adserver\Uploader\Image\ImageUploader;
use Adshares\Adserver\Uploader\Model\ModelUploader;
use Adshares\Adserver\Uploader\Video\VideoUploader;
use Adshares\Adserver\Uploader\Html\HtmlUploader;
use Illuminate\Http\Request;

final class FactoryTest extends TestCase
{
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
            ['html', HtmlUploader::class],
            ['video', VideoUploader::class],
            ['model', ModelUploader::class],
            ['image', ImageUploader::class],
            ['direct', DirectLinkUploader::class],
            ['default', ImageUploader::class],
        ];
    }
}
