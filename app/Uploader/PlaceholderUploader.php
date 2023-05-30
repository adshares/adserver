<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Uploader;

use Adshares\Adserver\Http\Requests\Campaign\BannerValidator;
use Adshares\Adserver\Services\Supply\BannerPlaceholderProvider;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Domain\Model\Banner;
use Adshares\Supply\Domain\ValueObject\Size;
use Illuminate\Http\UploadedFile;

class PlaceholderUploader
{
    public function __construct(private readonly BannerPlaceholderProvider $provider)
    {
    }

    public function upload(UploadedFile $file, Medium $medium): string
    {
        $size = $file->getSize();
        if (!$size || $size > config('app.upload_limit_image')) {
            throw new RuntimeException('Invalid image size');
        }
        $imageSize = getimagesize($file->getRealPath());
        $width = $imageSize[0];
        $height = $imageSize[1];

        $scope = Size::fromDimensions($width, $height);
        $bannerValidator = new BannerValidator($medium);
        $bannerValidator->validateScope(Banner::TYPE_IMAGE, $scope);
        $mimeType = $file->getMimeType();
        $bannerValidator->validateMimeType(Banner::TYPE_IMAGE, $mimeType);

        $placeholder = $this->provider->addBannerPlaceholder(
            $medium->getName(),
            $medium->getVendor(),
            $scope,
            Banner::TYPE_IMAGE,
            $mimeType,
            $file->getContent(),
        );
        return $placeholder->uuid;
    }
}
