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

namespace Adshares\Adserver\Services\Supply;

use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Lib\ZipToHtml;
use Adshares\Supply\Domain\Model\Banner;
use FFMpeg\Exception\ExecutableNotFoundException;
use FFMpeg\FFMpeg;
use FFMpeg\Format\Video\X264;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Imagick;
use ZipArchive;

class BannerPlaceholderConverter
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
    private const MIME_TYPE_TEXT_HTML = 'text/html';
    private const MIME_TYPE_VIDEO_MP4 = 'video/mp4';

    public function __construct(private readonly BannerPlaceholderProvider $provider)
    {
    }

    public function convertToImages(
        UploadedFile $file,
        Medium $medium,
        string $scope,
        string $groupUuid,
        bool $isDefault = false,
        bool $forceOverwrite = false,
    ): void {
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
            if ($isDefault) {
                $this->provider->addDefaultBannerPlaceholder(
                    $medium->getName(),
                    $scope,
                    Banner::TYPE_IMAGE,
                    $mime,
                    $imagick->getImageBlob(),
                    $groupUuid,
                    $forceOverwrite,
                );
            } else {
                $this->provider->addBannerPlaceholder(
                    $medium->getName(),
                    $scope,
                    Banner::TYPE_IMAGE,
                    $mime,
                    $imagick->getImageBlob(),
                    $groupUuid,
                );
            }
        }
    }

    public function convertToHtml(
        UploadedFile $file,
        Medium $medium,
        string $scope,
        string $groupUuid,
        bool $isDefault = false,
        bool $forceOverwrite = false,
    ): void {
        $imagick = new Imagick();
        $imagick->readImageBlob($file->getContent());
        $imagick->setImageFormat('PNG');

        $zip = new ZipArchive();
        $outFile = $file->getRealPath() . '.zip';
        if (true !== $zip->open($outFile, ZipArchive::CREATE)) {
            Log::error('Cannot convert to html: cannot create zip file');
            return;
        }
        $zip->addFromString('index.html', sprintf(self::HTML_TEMPLATE, base64_encode($imagick->getImageBlob())));
        $zip->close();

        try {
            $zipToHtml = new ZipToHtml($outFile);
            $content = $zipToHtml->getHtml();
        } catch (RuntimeException $exception) {
            Log::error(sprintf('Cannot convert to html: %s', $exception->getMessage()));
            return;
        }

        if ($isDefault) {
            $this->provider->addDefaultBannerPlaceholder(
                $medium->getName(),
                $scope,
                Banner::TYPE_HTML,
                self::MIME_TYPE_TEXT_HTML,
                $content,
                $groupUuid,
                $forceOverwrite,
            );
        } else {
            $this->provider->addBannerPlaceholder(
                $medium->getName(),
                $scope,
                Banner::TYPE_HTML,
                self::MIME_TYPE_TEXT_HTML,
                $content,
                $groupUuid,
            );
        }
        unlink($outFile);
    }

    public function convertToVideos(
        UploadedFile $file,
        Medium $medium,
        string $scope,
        string $groupUuid,
        bool $isDefault = false,
        bool $forceOverwrite = false,
    ): void {
        $videoMimes = $this->getSupportedMimesForBannerType($medium, Banner::TYPE_VIDEO);
        if (in_array(self::MIME_TYPE_VIDEO_MP4, $videoMimes, true)) {
            $outFile = $file->getRealPath() . '.mp4';
            try {
                $ffmpeg = FFMpeg::create();
                $loaded = $ffmpeg->open($file->getRealPath());
                $loaded->save(new X264(), $outFile);

                if ($isDefault) {
                    $this->provider->addDefaultBannerPlaceholder(
                        $medium->getName(),
                        $scope,
                        Banner::TYPE_VIDEO,
                        self::MIME_TYPE_VIDEO_MP4,
                        file_get_contents($outFile),
                        $groupUuid,
                        $forceOverwrite,
                    );
                } else {
                    $this->provider->addBannerPlaceholder(
                        $medium->getName(),
                        $scope,
                        Banner::TYPE_VIDEO,
                        self::MIME_TYPE_VIDEO_MP4,
                        file_get_contents($outFile),
                        $groupUuid,
                    );
                }
                unlink($outFile);
            } catch (ExecutableNotFoundException $exception) {
                Log::critical(sprintf('Check if ffmpeg is installed in system (%s)', $exception->getMessage()));
            }
        }
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

    public function convert(UploadedFile $file, Medium $medium, string $scope, string $groupUuid): void
    {
        foreach ($medium->getFormats() as $format) {
            switch ($format->getType()) {
                case Banner::TYPE_IMAGE:
                    if (isset($format->getScopes()[$scope])) {
                        $this->convertToImages($file, $medium, $scope, $groupUuid);
                    }
                    break;
                case Banner::TYPE_HTML:
                    if (isset($format->getScopes()[$scope])) {
                        $this->convertToHtml($file, $medium, $scope, $groupUuid);
                    }
                    break;
                case Banner::TYPE_VIDEO:
                    if (isset($format->getScopes()[$scope])) {
                        $this->convertToVideos($file, $medium, $scope, $groupUuid);
                    }
                    break;
                default:
                    break;
            }
        }
    }
}
