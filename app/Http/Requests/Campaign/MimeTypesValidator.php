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
use Adshares\Common\Application\Dto\TaxonomyV2;
use Adshares\Common\Exception\InvalidArgumentException;

class MimeTypesValidator
{
    private TaxonomyV2 $taxonomy;

    public function __construct(TaxonomyV2 $taxonomy)
    {
        $this->taxonomy = $taxonomy;
    }

    /**
     * @param Banner[]|array $banners
     * @param string $mediumName
     * @param string|null $vendor
     * @return void
     */
    public function validateMimeTypes(array $banners, string $mediumName = 'web', ?string $vendor = null): void
    {
        $actual = $this->getActualMimesByBannerType($banners);
        $supported = $this->getSupportedMimesByBannerType($mediumName, $vendor);

        foreach ($actual as $bannerType => $mimes) {
            if (!array_key_exists($bannerType, $supported)) {
                throw new InvalidArgumentException(sprintf('Not supported ad type `%s`', $bannerType));
            }

            $arrayDiff = array_diff(array_unique($mimes), $supported[$bannerType]);
            if (!empty($arrayDiff)) {
                throw new InvalidArgumentException(
                    sprintf('Not supported ad mime type `%s` for %s banner', join(', ', $arrayDiff), $bannerType)
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

    private function getSupportedMimesByBannerType(string $mediumName, ?string $vendor): array
    {
        $supported = [];
        foreach ($this->taxonomy->getMedia() as $medium) {
            if ($medium->getName() === $mediumName && $medium->getVendor() === $vendor) {
                foreach ($medium->getFormats() as $format) {
                    $supported[$format->getType()] = $format->getMimes();
                }
                break;
            }
        }
        return $supported;
    }
}
