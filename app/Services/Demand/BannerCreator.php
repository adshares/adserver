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

namespace Adshares\Adserver\Services\Demand;

use Adshares\Adserver\Http\Requests\Campaign\BannerValidator;
use Adshares\Adserver\Http\Requests\Campaign\MimeTypesValidator;
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Uploader\Image\ImageUploader;
use Adshares\Adserver\Uploader\Model\ModelUploader;
use Adshares\Adserver\Uploader\Video\VideoUploader;
use Adshares\Adserver\Uploader\Zip\ZipUploader;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Common\Exception\RuntimeException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class BannerCreator
{
    public function __construct(private readonly ConfigurationRepository $configurationRepository)
    {
    }

    /**
     * @param array $input
     * @param Campaign $campaign
     * @return Banner[]|array
     */
    public function prepareBannersFromInput(array $input, Campaign $campaign): array
    {
        $medium = $this->configurationRepository->fetchMedium($campaign->medium, $campaign->vendor);
        $bannerValidator = new BannerValidator($medium);

        $banners = [];

        foreach ($input as $banner) {
            if (!is_array($banner)) {
                throw new InvalidArgumentException('Invalid banner data type');
            }
            $bannerValidator->validateBanner($banner);
            $bannerModel = new Banner();
            $bannerModel->name = $banner['name'];
            $bannerModel->status = Banner::STATUS_ACTIVE;
            $size = $banner['creative_size'];
            $type = $banner['creative_type'];
            $bannerModel->creative_size = $size;
            $bannerModel->creative_type = $type;

            try {
                switch ($type) {
                    case Banner::TEXT_TYPE_IMAGE:
                        $fileName = Utils::extractFilename($banner['url']);
                        $content = ImageUploader::content($fileName);
                        $mimeType = ImageUploader::contentMimeType($fileName);
                        break;
                    case Banner::TEXT_TYPE_VIDEO:
                        $fileName = Utils::extractFilename($banner['url']);
                        $content = VideoUploader::content($fileName);
                        $mimeType = VideoUploader::contentMimeType($fileName);
                        break;
                    case Banner::TEXT_TYPE_MODEL:
                        $fileName = Utils::extractFilename($banner['url']);
                        $content = ModelUploader::content($fileName);
                        $mimeType = ModelUploader::contentMimeType($content);
                        break;
                    case Banner::TEXT_TYPE_HTML:
                        $content = ZipUploader::content(Utils::extractFilename($banner['url']));
                        $mimeType = 'text/html';
                        break;
                    case Banner::TEXT_TYPE_DIRECT_LINK:
                    default:
                        $content = Utils::appendFragment(
                            empty($banner['creative_contents']) ? $campaign->landing_url : $banner['creative_contents'],
                            $size
                        );
                        $mimeType = 'text/plain';
                        break;
                }
            } catch (FileNotFoundException $exception) {
                throw new UnprocessableEntityHttpException($exception->getMessage());
            } catch (RuntimeException $exception) {
                Log::debug(
                    sprintf(
                        'Banner (name: %s, type: %s) could not be added (%s).',
                        $banner['name'],
                        $type,
                        $exception->getMessage()
                    )
                );

                continue;
            }

            $bannerModel->creative_contents = $content;
            $bannerModel->creative_mime = $mimeType;

            $banners[] = $bannerModel;
        }

        $mimesValidator = new MimeTypesValidator($medium);
        $mimesValidator->validateMimeTypes($banners);

        return $banners;
    }

    public function updateBanner(array $input, Banner $banner): Banner
    {
        if (array_key_exists('name', $input)) {
            BannerValidator::validateName($input['name']);
            $banner->name = $input['name'];
        }

        if (array_key_exists('status', $input)) {
            if (!is_int($input['status'])) {
                throw new InvalidArgumentException('Field `status` must be an integer');
            }
            if (Banner::STATUS_REJECTED === $banner->status) {
                throw new InvalidArgumentException('Banner was rejected');
            }
            if (!Banner::isStatusAllowed($input['status'])) {
                throw new InvalidArgumentException(sprintf('Invalid status (%d)', $input['status']));
            }
            $banner->status = $input['status'];
        }

        return $banner;
    }
}
