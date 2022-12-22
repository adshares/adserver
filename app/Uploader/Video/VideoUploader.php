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

namespace Adshares\Adserver\Uploader\Video;

use Adshares\Adserver\Models\UploadedFile as UploadedFileModel;
use Adshares\Adserver\Uploader\UploadedFile;
use Adshares\Adserver\Uploader\Uploader;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Domain\ValueObject\Size;
use getID3;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VideoUploader implements Uploader
{
    public const VIDEO_FILE = 'video';
    private const VIDEO_DISK = 'banners';
    private const FORMAT_TYPE_VIDEO = 'video';

    public function __construct(private readonly Request $request)
    {
    }

    public function upload(Medium $medium): UploadedFile
    {
        $file = $this->request->file('file');
        $size = $file->getSize();
        if (!$size || $size > config('app.upload_limit_video')) {
            throw new RuntimeException('Invalid video size');
        }
        $fileInfo = (new getID3())->analyze($file->getRealPath());
        $width = $fileInfo['video']['resolution_x'];
        $height = $fileInfo['video']['resolution_y'];

        $this->validateDimensions($medium, $width, $height);

        $model = new UploadedFileModel([
            'medium' => $medium->getName(),
            'vendor' => $medium->getVendor(),
            'mime' => $file->getMimeType(),
            'scope' => Size::fromDimensions($width, $height),
            'content' => $file->getContent(),
        ]);
        $model->saveOrFail();

        $name = $model->ulid;
        $previewUrl = new SecureUrl(
            route('app.campaigns.upload_preview', ['type' => self::VIDEO_FILE, 'uid' => $name])
        );

        return new UploadedVideo($name, $previewUrl->toString(), $width, $height);
    }

    private function validateDimensions(Medium $medium, $width, $height): void
    {
        if (empty(Size::findMatchingWithSizes($this->extractSizesFromMedium($medium), $width, $height))) {
            throw new RuntimeException('Unsupported video size');
        }
    }

    private function extractSizesFromMedium(Medium $medium): array
    {
        foreach ($medium->getFormats() as $format) {
            if (self::FORMAT_TYPE_VIDEO === $format->getType()) {
                return array_keys($format->getScopes());
            }
        }
        return [];
    }

    public function removeTemporaryFile(string $fileName): void
    {
        try {
            UploadedFileModel::fetchByUlidOrFail($fileName)->delete();
        } catch (ModelNotFoundException $exception) {
            Log::warning(sprintf('Exception during video file deletion (%s)', $exception->getMessage()));
        }
    }

    public function preview(string $fileName): Response
    {
        $file = UploadedFileModel::fetchByUlidOrFail($fileName);
        $response = new Response($file->content);
        $response->header('Content-Type', $file->mime);

        return $response;
    }

    public static function content(string $fileName): string
    {
        $content = Storage::disk(self::VIDEO_DISK)->get($fileName);
        if (null === $content) {
            throw new FileNotFoundException(sprintf('File %s cannot be found', $fileName));
        }
        return $content;
    }

    public static function contentMimeType(string $fileName): string
    {
        if (!Storage::disk(self::VIDEO_DISK)->exists($fileName)) {
            throw new FileNotFoundException(sprintf('File `%s` does not exist', $fileName));
        }
        $path = Storage::disk(self::VIDEO_DISK)->path($fileName);

        return mime_content_type($path);
    }
}
