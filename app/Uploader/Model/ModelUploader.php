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

namespace Adshares\Adserver\Uploader\Model;

use Adshares\Adserver\Http\Requests\Campaign\BannerValidator;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\UploadedFile as UploadedFileModel;
use Adshares\Adserver\Uploader\UploadedFile;
use Adshares\Adserver\Uploader\Uploader;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Exception\RuntimeException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModelUploader extends Uploader
{
    public function __construct(private readonly Request $request)
    {
    }

    public function upload(Medium $medium): UploadedFile
    {
        $file = $this->request->file('file');
        if (null === $file) {
            throw new RuntimeException('Field `file` is required');
        }
        $size = $file->getSize();
        if (!$size || $size > config('app.upload_limit_model')) {
            throw new RuntimeException('Invalid model size');
        }

        $content = $file->getContent();
        $scope = 'cube';
        $bannerValidator = new BannerValidator($medium);
        $bannerValidator->validateScope(Banner::TEXT_TYPE_MODEL, $scope);
        $mimeType = self::contentMimeType($content);
        $bannerValidator->validateMimeType(Banner::TEXT_TYPE_MODEL, $mimeType);

        $model = new UploadedFileModel([
            'type' => Banner::TEXT_TYPE_MODEL,
            'medium' => $medium->getName(),
            'vendor' => $medium->getVendor(),
            'mime' => $mimeType,
            'scope' => $scope,
            'content' => $content,
        ]);
        Auth::user()->uploadedFiles()->save($model);

        $name = $model->uuid;
        $previewUrl = new SecureUrl(
            route('app.campaigns.upload_preview', ['type' => Banner::TEXT_TYPE_MODEL, 'uuid' => $name])
        );

        return new UploadedModel($name, $previewUrl->toString());
    }

    private static function contentMimeType(string $content): string
    {
        $fileHeader = substr($content, 0, 4);
        return match ($fileHeader) {
            'glTF' => 'model/gltf-binary',
            'VOX ' => 'model/voxel',
            default => throw new RuntimeException('Unsupported model file'),
        };
    }
}
