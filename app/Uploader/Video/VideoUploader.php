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

use Adshares\Adserver\Http\Requests\Campaign\BannerValidator;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\UploadedFile as UploadedFileModel;
use Adshares\Adserver\Uploader\UploadedFile;
use Adshares\Adserver\Uploader\Uploader;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Domain\ValueObject\SecureUrl;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Domain\ValueObject\Size;
use FFMpeg\Exception\ExecutableNotFoundException;
use FFMpeg\FFProbe;
use getID3;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class VideoUploader extends Uploader
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
        if (!$size || $size > config('app.upload_limit_video')) {
            throw new RuntimeException('Invalid video size');
        }
        [$width, $height] = $this->getVideoDimensions($file->getRealPath());

        $scope = Size::fromDimensions($width, $height);
        $bannerValidator = new BannerValidator($medium);
        $bannerValidator->validateScope(Banner::TEXT_TYPE_VIDEO, $scope);
        $mimeType = $file->getMimeType();
        $bannerValidator->validateMimeType(Banner::TEXT_TYPE_VIDEO, $mimeType);

        $model = new UploadedFileModel([
            'type' => Banner::TEXT_TYPE_VIDEO,
            'medium' => $medium->getName(),
            'vendor' => $medium->getVendor(),
            'mime' => $mimeType,
            'scope' => $scope,
            'content' => $file->getContent(),
        ]);
        Auth::user()->uploadedFiles()->save($model);

        $name = $model->uuid;
        $previewUrl = new SecureUrl(
            route('app.campaigns.upload_preview', ['type' => Banner::TEXT_TYPE_VIDEO, 'uuid' => $name])
        );

        return new UploadedVideo($name, $previewUrl->toString(), $width, $height);
    }

    private function getVideoDimensions(string $realPath): array
    {
        try {
            $probe = FFProbe::create();
        } catch (ExecutableNotFoundException $exception) {
            Log::critical(sprintf('Check if ffmpeg is installed in system (%s)', $exception->getMessage()));
            $fileInfo = (new getID3())->analyze($realPath);
            return [
                $fileInfo['video']['resolution_x'],
                $fileInfo['video']['resolution_y'],
            ];
        }

        $streams = $probe->streams($realPath);
        foreach ($streams as $stream) {
            if ('video' === $stream->get('codec_type')) {
                $dimensions = $stream->getDimensions();
                return [$dimensions->getWidth(), $dimensions->getHeight()];
            }
        }
        throw new RuntimeException('No video stream');
    }
}
