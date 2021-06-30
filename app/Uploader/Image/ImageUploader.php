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

namespace Adshares\Adserver\Uploader\Image;

use Adshares\Adserver\Uploader\UploadedFile;
use Adshares\Adserver\Uploader\Uploader;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Storage;

use function mime_content_type;

class ImageUploader implements Uploader
{
    public const IMAGE_FILE = 'image';
    private const IMAGE_DISK = 'banners';

    /** @var Request */
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function upload(): UploadedFile
    {
        $file = $this->request->file('file');
        $name = $file->store('', self::IMAGE_DISK);
        $previewUrl = new SecureUrl(
            route('app.campaigns.upload_preview', ['type' => self::IMAGE_FILE, 'name' => $name])
        );

        $imageSize = getimagesize($file->getRealPath());

        return new UploadedImage($name, $previewUrl->toString(), $imageSize);
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

        $response = new Response(file_get_contents($path), 200);
        $response->header('Content-Type', $mime);

        return $response;
    }

    public static function content(string $fileName): string
    {
        return Storage::disk(self::IMAGE_DISK)->get($fileName);
    }
}
