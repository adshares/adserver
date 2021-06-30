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

namespace Adshares\Adserver\Uploader\Zip;

use Adshares\Adserver\Uploader\UploadedFile;
use Adshares\Adserver\Uploader\Uploader;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Lib\ZipToHtml;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ZipUploader implements Uploader
{
    public const ZIP_FILE = 'zip';
    private const ZIP_DISK = 'banners';
    private $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function upload(): UploadedFile
    {
        $file = $this->request->file('file');
        $name = $file->store('', self::ZIP_DISK);
        $previewUrl = new SecureUrl(
            route('app.campaigns.upload_preview', ['type' => self::ZIP_FILE, 'name' => $name])
        );

        $this->validateContent($name);

        return new UploadedZip($name, $previewUrl->toString());
    }

    private function validateContent(string $name): void
    {
        $path = Storage::disk(self::ZIP_DISK)->path($name);
        $zip = new ZipToHtml($path);
        $zip->getHtml();
    }

    public function removeTemporaryFile(string $fileName): void
    {
        try {
            Storage::disk(self::ZIP_DISK)->delete($fileName);
        } catch (FileNotFoundException $exception) {
            Log::warning(sprintf('Removing ZIP file (%s) does not exist.', $fileName));
        }
    }

    public function preview(string $fileName): Response
    {
        $path = Storage::disk(self::ZIP_DISK)->path($fileName);

        $zip = new ZipToHtml($path);
        $html = $zip->getHtml();

        return new Response($html);
    }

    public static function content(string $fileName): string
    {
        $zip = new ZipToHtml(Storage::disk(self::ZIP_DISK)->path($fileName));

        return $zip->getHtml();
    }
}
