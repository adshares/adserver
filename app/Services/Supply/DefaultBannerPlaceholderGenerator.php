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

use Adshares\Adserver\Utilities\UuidStringGenerator;
use Adshares\Common\Application\Dto\TaxonomyV2\Format;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Application\Dto\TaxonomyV2\Targeting;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Domain\Adapter\ArrayableItemCollection;
use Adshares\Supply\Domain\Model\Banner;
use Adshares\Supply\Domain\ValueObject\Size;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickPixel;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Throwable;

class DefaultBannerPlaceholderGenerator
{
    public function __construct(
        private readonly BannerPlaceholderConverter $converter,
        private readonly BannerPlaceholderProvider $provider,
        private readonly ConfigurationRepository $repository,
    ) {
    }

    public function generate(bool $forceOverwrite = false): void
    {
        $media = $this->repository->fetchTaxonomy()->getMedia();

        foreach ($this->repository->fetchMedia()->toArray() as $mediumName => $mediumLabel) {
            $medium = self::mergeMediaByName($media, $mediumName);
            $formats = $medium->getFormats();
            DB::beginTransaction();
            try {
                foreach ($formats as $format) {
                    switch ($format->getType()) {
                        case Banner::TYPE_IMAGE:
                            foreach (array_keys($format->getScopes()) as $scope) {
                                $file = $this->createFile($scope);
                                $groupUuid = UuidStringGenerator::v4();
                                $this->provider->addDefaultBannerPlaceholder(
                                    $mediumName,
                                    $scope,
                                    Banner::TYPE_IMAGE,
                                    'image/png',
                                    $file->getContent(),
                                    $groupUuid,
                                    $forceOverwrite,
                                );
                                $this->converter->convertToImages(
                                    $file,
                                    $medium,
                                    $scope,
                                    $groupUuid,
                                    true,
                                    $forceOverwrite,
                                );
                            }
                            break;
                        case Banner::TYPE_HTML:
                            foreach (array_keys($format->getScopes()) as $scope) {
                                $this->converter->convertToHtml(
                                    $this->createFile($scope),
                                    $medium,
                                    $scope,
                                    UuidStringGenerator::v4(),
                                    true,
                                    $forceOverwrite,
                                );
                            }
                            break;
                        case Banner::TYPE_VIDEO:
                            foreach (array_keys($format->getScopes()) as $scope) {
                                $this->converter->convertToVideos(
                                    $this->createFile($scope),
                                    $medium,
                                    $scope,
                                    UuidStringGenerator::v4(),
                                    true,
                                    $forceOverwrite,
                                );
                            }
                            break;
                        default:
                            break;
                    }
                }
                DB::commit();
            } catch (Throwable $throwable) {
                DB::rollBack();
                Log::error(sprintf('Generating default banner placeholders failed: %s', $throwable->getMessage()));
                throw $throwable;
            }
        }
    }

    public static function mergeMediaByName(ArrayableItemCollection $media, string $mediumName): Medium
    {
        $formatsData = [];
        /** @var Medium $medium */
        foreach ($media as $medium) {
            if ($medium->getName() === $mediumName) {
                foreach ($medium->getFormats() as $format) {
                    if (isset($formatsData[$format->getType()])) {
                        $formatsData[$format->getType()]['mimes'] = array_unique(
                            array_merge(
                                $formatsData[$format->getType()]['mimes'],
                                $format->getMimes(),
                            )
                        );
                        $formatsData[$format->getType()]['scopes'] = array_unique(
                            array_merge(
                                $formatsData[$format->getType()]['scopes'],
                                $format->getScopes(),
                            )
                        );
                    } else {
                        $formatsData[$format->getType()] = [
                            'type' => $format->getType(),
                            'mimes' => $format->getMimes(),
                            'scopes' => $format->getScopes(),
                        ];
                    }
                }
            }
        }
        $formats = new ArrayableItemCollection();
        foreach ($formatsData as $formatData) {
            $formats->add(Format::fromArray($formatData));
        }
        return new Medium(
            $mediumName,
            $mediumName,
            null,
            null,
            $formats,
            new Targeting(
                new ArrayableItemCollection(),
                new ArrayableItemCollection(),
                new ArrayableItemCollection(),
            )
        );
    }

    private function createFile(string $size): UploadedFile
    {
        $fileName = 'seed.png';
        $path = Storage::disk('local')->path($fileName);

        $color = config('app.supply_placeholder_color');
        $file = config('app.supply_placeholder_file');

        [$width, $height] = Size::toDimensions($size);

        $image = new Imagick();
        $image->newImage($width, $height, new ImagickPixel('#' . $color), 'png');

        $logoCopy = new Imagick($file);
        $logoCopy->scaleImage($width, $height, true);

        $image->compositeImage(
            $logoCopy,
            Imagick::COMPOSITE_DEFAULT,
            (int)ceil(($width - $logoCopy->getImageWidth()) / 2),
            (int)ceil(($height - $logoCopy->getImageHeight()) / 2),
        );

        $image->writeImage($path);

        return UploadedFile::createFromBase(new SymfonyUploadedFile($path, $fileName, 'image/png'));
    }
}
