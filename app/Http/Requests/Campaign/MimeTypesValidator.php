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

namespace Adshares\Adserver\Http\Requests\Campaign;

use Adshares\Adserver\Models\Banner;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Exception\InvalidArgumentException;

class MimeTypesValidator
{
    public function __construct(private readonly Medium $medium)
    {
    }

    /**
     * @param Banner[]|array $banners
     * @return void
     */
    public function validateMimeTypes(array $banners): void
    {
        $actual = $this->getActualMimesByBannerType($banners);
        $supported = $this->getSupportedMimesByBannerType();

        foreach ($actual as $bannerType => $mimes) {
            if (!array_key_exists($bannerType, $supported)) {
                throw new InvalidArgumentException(sprintf('Not supported ad type `%s`', $bannerType));
            }

            $arrayDiff = array_diff(array_unique($mimes), $supported[$bannerType]);
            if (!empty($arrayDiff)) {
                throw new InvalidArgumentException(
                    sprintf('Not supported ad mime type `%s` for %s creative', join(', ', $arrayDiff), $bannerType)
                );
            }
        }
    }

    private function getActualMimesByBannerType(array $banners): array
    {
        $mimesByBannerType = [];
        foreach ($banners as $banner) {
            if (!isset($mimesByBannerType[$banner->creative_type])) {
                $mimesByBannerType[$banner->creative_type] = [];
            }
            $mimesByBannerType[$banner->creative_type][] = $banner->creative_mime;
        }
        return $mimesByBannerType;
    }

    private function getSupportedMimesByBannerType(): array
    {
        $supported = [];
        foreach ($this->medium->getFormats() as $format) {
            $supported[$format->getType()] = $format->getMimes();
        }
        return $supported;
    }

    private function getSupportedMimesForBannerType(string $type): array
    {
        foreach ($this->medium->getFormats() as $format) {
            if ($format->getType() === $type) {
                return $format->getMimes();
            }
        }
        throw new InvalidArgumentException(sprintf('Not supported ad type `%s`', $type));
    }

    public function validateMimeTypeForBannerType(string $bannerType, ?string $mimeType): void
    {
        if (null === $mimeType) {
            throw new InvalidArgumentException('Unknown mime');
        }
        if (!in_array($mimeType, $this->getSupportedMimesForBannerType($bannerType), true)) {
            throw new InvalidArgumentException(
                sprintf('Not supported ad mime type `%s` for %s creative', $mimeType, $bannerType)
            );
        }
    }
}
