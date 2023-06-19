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
use Adshares\Lib\ZipToHtml;
use Adshares\Supply\Domain\Model\Banner;
use Adshares\Supply\Domain\ValueObject\Size;
use FFMpeg\Exception\ExecutableNotFoundException;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Imagick;
use Throwable;
use ZipArchive;

class PlaceholderUploader
{
    private const HTML_TEMPLATE = <<<HTML
<html lang="en">
  <head>
    <title>placeholder</title>
  </head>
  <body>
    <img src="data:image/png;base64,%s" alt="">
  </body>
</html>
HTML;

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

        DB::beginTransaction();
        try {
            $placeholder = $this->provider->addBannerPlaceholder(
                $medium->getName(),
                $medium->getVendor(),
                $scope,
                Banner::TYPE_IMAGE,
                $mimeType,
                $file->getContent(),
            );
            $placeholderUuid = $placeholder->uuid;
            $this->convertToImages($placeholderUuid, $file, $medium, $scope);
            $this->convertToHtml($placeholderUuid, $file, $medium, $scope);
            $this->convertToVideos($placeholderUuid, $file, $medium, $scope);
            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error(sprintf('Cannot store placeholder: %s', $throwable->getMessage()));
            throw new RuntimeException('Cannot store placeholder');
        }

        return $placeholderUuid;
    }

    private function getSupportedMimesForBannerType(Medium $medium, string $type): array
    {
        $mimes = [];
        foreach ($medium->getFormats() as $format) {
            if ($type === $format->getType()) {
                $mimes = $format->getMimes();
                break;
            }
        }
        return $mimes;
    }

    private function convertToImages(string $placeholderUuid, UploadedFile $file, Medium $medium, string $scope): void
    {
        $mimeType = $file->getMimeType();
        $imagick = new Imagick();
        $imagick->readImageBlob($file->getContent());
        $imagickFormats = $imagick->queryFormats();
        foreach ($this->getSupportedMimesForBannerType($medium, Banner::TYPE_IMAGE) as $mime) {
            if ($mimeType === $mime) {
                continue;
            }
            $format = strtoupper(explode('/', $mime, 2)[1]);
            if (!in_array($format, $imagickFormats, true)) {
                Log::error(sprintf('Cannot convert to mime %s', $mime));
                continue;
            }
            $imagick->setImageFormat($format);
            $this->provider->addBannerPlaceholder(
                $medium->getName(),
                $medium->getVendor(),
                $scope,
                Banner::TYPE_IMAGE,
                $mime,
                $imagick->getImageBlob(),
                parentUuid: $placeholderUuid,
            );
        }
    }

    private function convertToHtml(string $placeholderUuid, UploadedFile $file, Medium $medium, string $scope): void
    {
        $imagick = new Imagick();
        $imagick->readImageBlob($file->getContent());
        $imagick->setImageFormat('PNG');

        $zip = new ZipArchive();
        $outFile = $file->getRealPath() . '.zip';
        if (true !== $zip->open($outFile, ZipArchive::CREATE)) {
            Log::error('Cannot convert to html: cannot create zip file');
            return;
        }
        $zip->addFromString(
            'index.html',
            sprintf(self::HTML_TEMPLATE, base64_encode($imagick->getImageBlob())),
        );
        $zip->close();

        try {
            $zipToHtml = new ZipToHtml($outFile);
            $content = $zipToHtml->getHtml();
        } catch (RuntimeException $exception) {
            Log::error(sprintf('Cannot convert to html: %s', $exception->getMessage()));
            return;
        }

        $this->provider->addBannerPlaceholder(
            $medium->getName(),
            $medium->getVendor(),
            $scope,
            Banner::TYPE_HTML,
            'text/html',
            $content,
            parentUuid: $placeholderUuid,
        );
        unlink($outFile);
    }

    private function convertToVideos(string $placeholderUuid, UploadedFile $file, Medium $medium, string $scope): void
    {
        $videoMimes = $this->getSupportedMimesForBannerType($medium, Banner::TYPE_VIDEO);
        if (in_array('video/mp4', $videoMimes, true)) {
            $outFile = $file->getRealPath() . '.mp4';
            try {
                $ffmpeg = FFMpeg::create();
                $loaded = $ffmpeg->open($file->getRealPath());
                $loaded->save(new X264(), $outFile);

                $this->provider->addBannerPlaceholder(
                    $medium->getName(),
                    $medium->getVendor(),
                    $scope,
                    Banner::TYPE_VIDEO,
                    'video/mp4',
                    file_get_contents($outFile),
                    parentUuid: $placeholderUuid,
                );
                unlink($outFile);
            } catch (ExecutableNotFoundException $exception) {
                Log::critical(sprintf('Check if ffmpeg is installed in system (%s)', $exception->getMessage()));
            }
        }
    }
}
