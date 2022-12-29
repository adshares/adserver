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

use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\UploadedFile as UploadedFileModel;
use Adshares\Adserver\Uploader\UploadedFile;
use Adshares\Adserver\Uploader\Uploader;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Domain\ValueObject\Size;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImageUploader extends Uploader
{
    private const FORMAT_TYPE_IMAGE = 'image';

    public function __construct(private readonly Request $request)
    {
    }

    public function upload(Medium $medium, string $scope = null): UploadedFile
    {
        $file = $this->request->file('file');
        if (null === $file) {
            throw new RuntimeException('Field `file` is required');
        }
        $size = $file->getSize();
        if (!$size || $size > config('app.upload_limit_image')) {
            throw new RuntimeException('Invalid image size');
        }
        $imageSize = getimagesize($file->getRealPath());
        $width = $imageSize[0];
        $height = $imageSize[1];
        $this->validateDimensions($medium, $width, $height);

        $model = new UploadedFileModel([
            'type' => Banner::TEXT_TYPE_IMAGE,
            'medium' => $medium->getName(),
            'vendor' => $medium->getVendor(),
            'mime' => $file->getMimeType(),
            'size' => Size::fromDimensions($width, $height),
            'content' => $file->getContent(),
        ]);
        Auth::user()->uploadedFiles()->save($model);

        $name = $model->uuid;
        $previewUrl = new SecureUrl(
            route('app.campaigns.upload_preview', ['type' => Banner::TEXT_TYPE_IMAGE, 'uuid' => $name])
        );

        return new UploadedImage($name, $previewUrl->toString(), $width, $height);
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
