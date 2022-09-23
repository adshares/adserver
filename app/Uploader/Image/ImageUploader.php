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

namespace Adshares\Adserver\Uploader\Image;

use Adshares\Adserver\Uploader\UploadedFile;
use Adshares\Adserver\Uploader\Uploader;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Domain\ValueObject\Size;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use function mime_content_type;

class ImageUploader implements Uploader
{
    public const IMAGE_FILE = 'image';
    private const IMAGE_DISK = 'banners';
    private const FORMAT_TYPE_IMAGE = 'image';

    public function __construct(private readonly Request $request)
    {
    }

    public function upload(Medium $medium): UploadedFile
    {
        $file = $this->request->file('file');
        $size = $file->getSize();
        if (!$size || $size > config('app.upload_limit_image')) {
            throw new RuntimeException('Invalid image size');
        }
        $imageSize = getimagesize($file->getRealPath());
        $width = $imageSize[0];
        $height = $imageSize[1];
        $this->validateDimensions($medium, $width, $height);

        $name = $file->store('', self::IMAGE_DISK);
        $previewUrl = new SecureUrl(
            route('app.campaigns.upload_preview', ['type' => self::IMAGE_FILE, 'name' => $name])
        );

        return new UploadedImage($name, $previewUrl->toString(), $width, $height);
    }

    public function removeTemporaryFile(string $fileName): void
    {
        try {
            Storage::disk(self::IMAGE_DISK)->delete($fileName);
        } catch (FileNotFoundException $exception) {
            Log::warning(sprintf('Removing IMAGE file (%s) does not exist.', $fileName));
        }
    }

    public function preview(string $fileName): Response
    {
        $path = Storage::disk(self::IMAGE_DISK)->path($fileName);
        $mime = mime_content_type($path);

        $response = new Response(file_get_contents($path));
        $response->header('Content-Type', $mime);

        return $response;
    }

    public static function content(string $fileName): string
    {
        return Storage::disk(self::IMAGE_DISK)->get($fileName);
    }

    public static function contentMimeType(string $fileName): string
    {
        $path = Storage::disk(self::IMAGE_DISK)->path($fileName);

        return mime_content_type($path);
    }

    private function validateDimensions(Medium $medium, int $width, int $height): void
    {
        $size = Size::fromDimensions($width, $height);
        foreach ($medium->getFormats() as $format) {
            if (self::FORMAT_TYPE_IMAGE === $format->getType() && in_array($size, array_keys($format->getScopes()))) {
                return;
            }
        }

        throw new RuntimeException('Unsupported image size');
    }
}
