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

namespace Adshares\Adserver\Uploader;

use Adshares\Adserver\Uploader\Image\ImageUploader;
use Adshares\Adserver\Uploader\Model\ModelUploader;
use Adshares\Adserver\Uploader\Video\VideoUploader;
use Adshares\Adserver\Uploader\Zip\ZipUploader;
use Illuminate\Http\Request;

class Factory
{
    private const EXTENSION_VIDEO_LIST = [
        'mp4',
    ];

    private const EXTENSION_MODEL_LIST = [
        'glb',
        'vox',
    ];

    private const MIME_VIDEO_LIST = [
        'video/mp4',
    ];

    private const MIME_ZIP_LIST = [
        'application/zip',
        'application/x-compressed',
        'multipart/x-zip',
        'application/octet-stream',
        'application/x-zip',
        'application/x-zip-compressed',
    ];

    public static function create(Request $request): Uploader
    {
        $file = $request->file('file');
        $mimeType = $file->getMimeType();

        if (in_array($mimeType, self::MIME_VIDEO_LIST, true)) {
            return new VideoUploader($request);
        }

        if ('application/octet-stream' === $mimeType) {
            $filePrefix = substr($file->get(), 0, 4);
            if ('glTF' === $filePrefix || 'VOX ' === $filePrefix) {
                return new ModelUploader($request);
            }
        }

        if (in_array($mimeType, self::MIME_ZIP_LIST, true)) {
            return new ZipUploader($request);
        }

        return new ImageUploader($request);
    }

    public static function createFromType(string $type, Request $request): Uploader
    {
        if ($type === ZipUploader::ZIP_FILE) {
            return new ZipUploader($request);
        }

        if ($type === VideoUploader::VIDEO_FILE) {
            return new VideoUploader($request);
        }

        if ($type === ModelUploader::MODEL_FILE) {
            return new ModelUploader($request);
        }

        return new ImageUploader($request);
    }

    public static function createFromExtension(string $fileName, Request $request)
    {
        $extension = self::getFileExtension($fileName);

        if ($extension === ZipUploader::ZIP_FILE) {
            return new ZipUploader($request);
        }

        if (in_array($extension, self::EXTENSION_VIDEO_LIST, true)) {
            return new VideoUploader($request);
        }

        if (in_array($extension, self::EXTENSION_MODEL_LIST, true)) {
            return new ModelUploader($request);
        }

        return new ImageUploader($request);
    }

    private static function getFileExtension(string $name): string
    {
        return substr($name, strrpos($name, '.') + 1);
    }
}
