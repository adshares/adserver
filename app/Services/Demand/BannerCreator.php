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
use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\UploadedFile;
use Adshares\Adserver\ViewModel\BannerStatus;
use Adshares\Common\Application\Service\ConfigurationRepository;
use Adshares\Common\Exception\InvalidArgumentException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Ramsey\Uuid\Uuid;

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
                throw new InvalidArgumentException('Invalid creative data type');
            }
            $banner = $this->changeLegacyFields($banner);
            $bannerValidator->validateBanner($banner);
            $bannerModel = new Banner();
            $bannerModel->name = $banner['name'];
            $bannerModel->status = Banner::STATUS_ACTIVE;
            $scope = $banner['scope'];
            $type = $banner['type'];
            $bannerModel->creative_size = $scope;
            $bannerModel->creative_type = $type;

            switch ($type) {
                case Banner::TEXT_TYPE_IMAGE:
                case Banner::TEXT_TYPE_VIDEO:
                case Banner::TEXT_TYPE_MODEL:
                case Banner::TEXT_TYPE_HTML:
                    $uuid = Uuid::fromString(Utils::extractFilename($banner['url']));
                    try {
                        $file = UploadedFile::fetchByUuidOrFail($uuid);
                    } catch (ModelNotFoundException) {
                        throw new InvalidArgumentException(sprintf('File `%s` does not exist', $uuid));
                    }
                    self::validateMediumMatch($campaign, $file);
                    if (null !== $file->scope && $scope !== $file->scope) {
                        throw new InvalidArgumentException(
                            sprintf('Scope `%s` does not match uploaded file', $scope)
                        );
                    }
                    $content = $file->content;
                    $mime = $file->mime;
                    break;
                case Banner::TEXT_TYPE_DIRECT_LINK:
                default:
                    $content = Utils::appendFragment(
                        empty($banner['contents']) ? $campaign->landing_url : $banner['contents'],
                        $scope
                    );
                    $mime = 'text/plain';
                    break;
            }

            $bannerModel->creative_contents = $content;
            $bannerModel->creative_mime = $mime;
            $banners[] = $bannerModel;
        }

        return $banners;
    }

    public function prepareBannersFromMetaData(array $metaData, Campaign $campaign): array
    {
        $medium = $this->configurationRepository->fetchMedium($campaign->medium, $campaign->vendor);
        $bannerValidator = new BannerValidator($medium);

        $banners = [];

        foreach ($metaData as $bannerMetaData) {
            if (!is_array($bannerMetaData)) {
                throw new InvalidArgumentException('Invalid creative data type');
            }
            $bannerValidator->validateBannerMetaData($bannerMetaData);
            $uuid = Uuid::fromString($bannerMetaData['file_id']);
            try {
                $file = UploadedFile::fetchByUuidOrFail($uuid);
            } catch (ModelNotFoundException) {
                throw new InvalidArgumentException(sprintf('File `%s` does not exist', $bannerMetaData['file_id']));
            }
            self::validateMediumMatch($campaign, $file);

            $bannerModel = new Banner();
            $bannerModel->name = $bannerMetaData['name'];
            $bannerModel->status = Banner::STATUS_ACTIVE;
            $bannerModel->creative_type = $file->type;
            $bannerModel->creative_mime = $file->mime;
            $bannerModel->creative_size = $file->scope;
            if (Banner::TEXT_TYPE_DIRECT_LINK === $file->type) {
                $bannerModel->creative_contents = Utils::appendFragment(
                    empty($file->content) ? $campaign->landing_url : $file->content,
                    $file->scope,
                );
            } else {
                $bannerModel->creative_contents = $file->content;
            }

            $banners[] = $bannerModel;
        }

        return $banners;
    }

    public function updateBanner(array $input, Banner $banner): Banner
    {
        if (array_key_exists('name', $input)) {
            BannerValidator::validateName($input['name']);
            $banner->name = $input['name'];
        }

        if (array_key_exists('status', $input)) {
            if (Banner::STATUS_REJECTED === $banner->status) {
                throw new InvalidArgumentException('Banner was rejected');
            }

            if (!is_int($input['status']) && !is_string($input['status'])) {
                throw new InvalidArgumentException('Field `status` must be an integer or string');
            }

            if (is_int($input['status'])) {
                if (!Banner::isStatusAllowed($input['status'])) {
                    throw new InvalidArgumentException('Field `status` must be one of supported states');
                }
                $banner->status = $input['status'];
            } else {
                try {
                    $status = BannerStatus::fromString($input['status']);
                } catch (InvalidArgumentException) {
                    throw new InvalidArgumentException('Field `status` must be one of supported states');
                }
                $banner->status = $status->value;
            }
        }

        return $banner;
    }

    private function changeLegacyFields(array $banner): array
    {
        foreach (
            [
                'creative_size' => 'scope',
                'creative_type' => 'type',
                'creative_contents' => 'contents',
            ] as $legacyField => $field
        ) {
            if (!isset($banner[$field]) && isset($banner[$legacyField])) {
                $banner[$field] = $banner[$legacyField];
            }
        }
        return $banner;
    }

    private static function validateMediumMatch(Campaign $campaign, UploadedFile $file): void
    {
        if ($campaign->medium !== $file->medium || $campaign->vendor !== $file->vendor) {
            throw new InvalidArgumentException("File's medium does not match campaign");
        }
    }
}
