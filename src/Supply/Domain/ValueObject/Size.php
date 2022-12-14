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

namespace Adshares\Supply\Domain\ValueObject;

use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Supply\Domain\Model\Banner;

final class Size
{
    public const TYPE_DISPLAY = 'display';
    public const TYPE_MODEL = 'model';
    public const TYPE_POP = 'pop';

    private const MINIMAL_ALLOWED_OCCUPIED_FIELD_FOR_MATCHING = 0.6;

    public static function findBestFit(
        Medium $medium,
        float $width,
        float $height,
        float $depth,
        float $minDpi,
        int $count = 5,
        ?string $zoneType = null,
    ): array {
        if (self::TYPE_POP === $zoneType) {
            return self::getScopesByTypes($medium, [Banner::TYPE_DIRECT_LINK]);
        }

        if ((null === $zoneType && $depth > 0) || self::TYPE_MODEL === $zoneType) {
            return self::getScopesByTypes($medium, [Banner::TYPE_MODEL]);
        }

        $scopes = self::getScopesByTypes($medium, [Banner::TYPE_HTML, Banner::TYPE_IMAGE, Banner::TYPE_VIDEO]);

        $sizes = array_map(
            function ($size) use ($width, $height, $minDpi) {
                [$x, $y] = explode("x", $size);

                $dpi = min($x / $width, $y / $height);
                if ($dpi < $minDpi) {
                    return false;
                }

                $score = 1 - min($x / $width, $y / $height) / max($x / $width, $y / $height);

                return [
                    'size' => $size,
                    'score' => $score,
                    'dpi' => $dpi,
                ];
            },
            $scopes
        );

        $sizes = array_filter($sizes);

        usort(
            $sizes,
            function ($a, $b) {
                if ($a['score'] == $b['score']) {
                    return $a['dpi'] > $b['dpi'] ? -1 : 1;
                }
                return ($a['score'] < $b['score']) ? -1 : 1;
            }
        );
        return array_map(
            function ($item) {
                return $item['size'];
            },
            array_slice($sizes, 0, $count)
        );
    }

    public static function fromDimensions(int $width, int $height): string
    {
        return sprintf('%dx%d', $width, $height);
    }

    public static function toDimensions(string $size): array
    {
        $parts = explode('x', $size);

        return [
            (int)($parts[0] ?? 0),
            (int)($parts[1] ?? 0),
        ];
    }

    public static function findMatchingWithSizes(
        array $sizes,
        int $width,
        int $height,
        float $minZoom = 0.25,
        float $maxZoom = 4.0
    ): array {
        if ($width <= 0 || $height <= 0) {
            return [];
        }

        return array_filter(
            $sizes,
            function ($size) use ($width, $height, $minZoom, $maxZoom) {
                [$x, $y] = self::toDimensions($size);

                $zoom = min($x / $width, $y / $height);
                if ($zoom < $minZoom || $zoom > $maxZoom) {
                    return false;
                }

                $occupiedField = $zoom * min($width / $x, $height / $y);

                return $occupiedField >= self::MINIMAL_ALLOWED_OCCUPIED_FIELD_FOR_MATCHING;
            }
        );
    }

    private static function getScopesByTypes(Medium $medium, array $types): array
    {
        $scopes = [];

        foreach ($medium->getFormats() as $format) {
            if (in_array($format->getType(), $types)) {
                $scopes = array_merge($scopes, $format->getScopes());
            }
        }
        return array_keys($scopes);
    }
}
