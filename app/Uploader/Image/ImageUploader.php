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

use Adshares\Adserver\Models\UploadedFile as UploadedFileModel;
use Adshares\Adserver\Uploader\UploadedFile;
use Adshares\Adserver\Uploader\Uploader;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Domain\ValueObject\Size;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ImageUploader implements Uploader
{
    public const IMAGE_FILE = 'image';
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

        $model = new UploadedFileModel([
            'medium' => $medium->getName(),
            'vendor' => $medium->getVendor(),
            'mime' => $file->getMimeType(),
            'size' => Size::fromDimensions($width, $height),
            'content' => $file->getContent(),
        ]);
        Auth::user()->uploadedFiles()->save($model);

        $name = $model->ulid;
        $previewUrl = new SecureUrl(
            route('app.campaigns.upload_preview', ['type' => self::IMAGE_FILE, 'uid' => $name])
        );

        return new UploadedImage($name, $previewUrl->toString(), $width, $height);
    }

    public function removeTemporaryFile(string $fileName): bool
    {
        try {
            UploadedFileModel::fetchByUlidOrFail($fileName)->delete();
            return true;
        } catch (ModelNotFoundException $exception) {
            Log::warning(sprintf('Exception during image file deletion (%s)', $exception->getMessage()));
            return false;
        }
    }

    public function preview(string $fileName): Response
    {
        $file = UploadedFileModel::fetchByUlidOrFail($fileName);
        $response = new Response($file->content);
        $response->header('Content-Type', $file->mime);

        return $response;
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
