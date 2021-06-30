<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Uploader;

use Adshares\Adserver\Uploader\Image\ImageUploader;
use Adshares\Adserver\Uploader\Zip\ZipUploader;
use Illuminate\Http\Request;

use function strrpos;
use function substr;

class Factory
{
    private const MIME_ZIP_LIST = [
        'application/zip',
        'application/x-compressed',
        'multipart/x-zip',
        'application/octet-stream',
        'application/x-zip-compressed',
    ];

    public static function create(Request $request): Uploader
    {
        $file = $request->file('file');

        if (in_array($file->getMimeType(), self::MIME_ZIP_LIST, true)) {
            return new ZipUploader($request);
        }

        return new ImageUploader($request);
    }

    public static function createFromType(string $type, Request $request): Uploader
    {
        if ($type === ZipUploader::ZIP_FILE) {
            return new ZipUploader($request);
        }

        return new ImageUploader($request);
    }

    public static function createFromExtension(string $fileName, Request $request)
    {
        if (self::isZipFile($fileName)) {
            return new ZipUploader($request);
        }

        return new ImageUploader($request);
    }

    private static function isZipFile(string $name): bool
    {
        return substr($name, strrpos($name, '.') + 1) === ZipUploader::ZIP_FILE;
    }
}
