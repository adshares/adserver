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

namespace Adshares\Adserver\Uploader\Zip;

use Adshares\Adserver\Models\UploadedFile as UploadedFileModel;
use Adshares\Adserver\Uploader\UploadedFile;
use Adshares\Adserver\Uploader\Uploader;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Lib\ZipToHtml;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ZipUploader implements Uploader
{
    public const ZIP_FILE = 'zip';
    private const ZIP_DISK = 'banners';

    public function __construct(private readonly Request $request)
    {
    }

    public function upload(Medium $medium): UploadedFile
    {
        $file = $this->request->file('file');
        $size = $file->getSize();
        if (!$size || $size > config('app.upload_limit_zip')) {
            throw new RuntimeException('Invalid zip size');
        }
        $name = $file->store('', self::ZIP_DISK);
        $content = $this->extractHtmlContent($name);
        $this->removeTemporaryFile($name);

        $model = new UploadedFileModel([
            'medium' => $medium->getName(),
            'vendor' => $medium->getVendor(),
            'mime' => 'text/html',
            'scope' => null,
            'content' => $content,
        ]);
        $model->saveOrFail();

        $name = $model->ulid;
        $previewUrl = new SecureUrl(
            route('app.campaigns.upload_preview', ['type' => self::ZIP_FILE, 'uid' => $name])
        );

        return new UploadedZip($name, $previewUrl->toString());
    }

    private function extractHtmlContent(string $name): string
    {
        $path = Storage::disk(self::ZIP_DISK)->path($name);
        $zip = new ZipToHtml($path);
        return $zip->getHtml();
    }

    public function removeTemporaryFile(string $fileName): void
    {
        try {
            UploadedFileModel::fetchByUlidOrFail($fileName)->delete();
        } catch (ModelNotFoundException $exception) {
            Log::warning(sprintf('Exception during zip file deletion (%s)', $exception->getMessage()));
        }
    }

    public function preview(string $fileName): Response
    {
        $file = UploadedFileModel::fetchByUlidOrFail($fileName);

        return new Response($file->content);
    }

    public static function content(string $fileName): string
    {
        if (!Storage::disk(self::ZIP_DISK)->exists($fileName)) {
            throw new FileNotFoundException(sprintf('File `%s` does not exist', $fileName));
        }
        $zip = new ZipToHtml(Storage::disk(self::ZIP_DISK)->path($fileName));

        return $zip->getHtml();
    }
}
