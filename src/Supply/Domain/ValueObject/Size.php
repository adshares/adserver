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

final class Size
{
    public const TYPE_DISPLAY = 'display';
    public const TYPE_MODEL = 'model';
    public const TYPE_POP = 'pop';

    private const CUBE = 'cube';

    public const SIZE_INFOS = [
        #best
        '300x250' => [
            'label' => 'Medium Rectangle',
            'tags' => ['Desktop', 'best'],
            'type' => self::TYPE_DISPLAY,
        ],
        '336x280' => [
            'label' => 'Large Rectangle',
            'tags' => ['Desktop', 'best'],
            'type' => self::TYPE_DISPLAY,
        ],
        '728x90' => [
            'label' => 'Leaderboard',
            'tags' => ['Desktop', 'best'],
            'type' => self::TYPE_DISPLAY,
        ],
        '300x600' => [
            'label' => 'Half Page',
            'tags' => ['Desktop', 'best'],
            'type' => self::TYPE_DISPLAY,
        ],
        '320x100' => [
            'label' => 'Large Mobile Banner',
            'tags' => ['Desktop', 'best', 'Mobile'],
            'type' => self::TYPE_DISPLAY,
        ],
        #other
        '320x50' => [
            'label' => 'Mobile Banner',
            'tags' => ['Desktop', 'Mobile'],
            'type' => self::TYPE_DISPLAY,
        ],
        '468x60' => [
            'label' => 'Full Banner',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '234x60' => [
            'label' => 'Half Banner',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '120x600' => [
            'label' => 'Skyscraper',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '120x240' => [
            'label' => 'Vertical Banner',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '160x600' => [
            'label' => 'Wide Skyscraper',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '300x1050' => [
            'label' => 'Portrait',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '970x90' => [
            'label' => 'Large Leaderboard',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '970x250' => [
            'label' => 'Billboard',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '250x250' => [
            'label' => 'Square',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '200x200' => [
            'label' => 'Small Square',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '180x150' => [
            'label' => 'Small Rectangle',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '125x125' => [
            'label' => 'Button',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        #regional
        '240x400' => [
            'label' => 'Vertical Rectangle',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '980x120' => [
            'label' => 'Panorama',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '250x360' => [
            'label' => 'Triple Widescreen',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '930x180' => [
            'label' => 'Top Banner',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '580x400' => [
            'label' => 'Netboard',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        #polish
        '750x100' => [
            'label' => 'Single Billboard',
            'tags' => ['Desktop', 'PL'],
            'type' => self::TYPE_DISPLAY,
        ],
        '750x200' => [
            'label' => 'Double Billboard',
            'tags' => ['Desktop', 'PL'],
            'type' => self::TYPE_DISPLAY,
        ],
        '750x300' => [
            'label' => 'Triple Billboard',
            'tags' => ['Desktop', 'PL'],
            'type' => self::TYPE_DISPLAY,
        ],
        # https://en.wikipedia.org/wiki/Web_banner
        '300x100' => [
            'label' => '3 to 1 Rectangle',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '120x90' => [
            'label' => 'Button One',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '120x60' => [
            'label' => 'Button Two',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '88x31' => [
            'label' => 'Micro Banner',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        'pop-up' => [
            'label' => 'Pop-up',
            'tags' => ['Desktop', 'Mobile'],
            'type' => self::TYPE_POP,
        ],
        'pop-under' => [
            'label' => 'Pop-under',
            'tags' => ['Desktop', 'Mobile'],
            'type' => self::TYPE_POP,
        ],
        self::CUBE => [
            'label' => 'Cube',
            'tags' => ['Metaverse'],
            'type' => self::TYPE_MODEL,
        ],
    ];

    private const MINIMAL_ALLOWED_OCCUPIED_FIELD_FOR_MATCHING = 0.6;

    public static function findBestFit(
        Medium $medium,
        float $width,
        float $height,
        float $depth,
        float $min_dpi,
        int $count = 5
    ): array {
        if ($depth > 0) {
            return [self::CUBE];
        }

        $scopes = [];
        foreach ($medium->getFormats() as $format) {
            if (in_array($format->getType(), ['image', 'video'])) {
                $scopes = array_merge($scopes, $format->getScopes());
            }
        }

        $sizes = array_map(
            function ($size) use ($width, $height, $min_dpi) {
                [$x, $y] = explode("x", $size);

                $dpi = min($x / $width, $y / $height);
                if ($dpi < $min_dpi) {
                    return false;
                }

                $score = 1 - min($x / $width, $y / $height) / max($x / $width, $y / $height);

                return [
                    'size' => $size,
                    'score' => $score,
                    'dpi' => $dpi,
                ];
            },
            array_keys($scopes)
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

    public static function getAspect(int $width, int $height): string
    {
        if ($width === 0 || $height === 0) {
            return '';
        }

        $a = $width;
        $b = $height;
        while ($b !== 0) {
            $c = $a % $b;
            $a = $b;
            $b = $c;
        }

        return $width / $a . ':' . $height / $a;
    }
}
