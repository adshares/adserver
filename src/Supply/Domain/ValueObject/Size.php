<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types = 1);

namespace Adshares\Supply\Domain\ValueObject;

use function in_array;

final class Size
{
    public const SUPPORTED_SIZES = [
        0 => '300x250',
        1 => '336x280',
        2 => '728x90',
        3 => '300x600',
        4 => '320x100',
        #other
        5 => '320x50',
        6 => '468x60',
        7 => '234x60',
        8 => '120x600',
        9 => '120x240',
        10 => '160x600',
        11 => '300x1050',
        12 => '970x90',
        13 => '970x250',
        14 => '250x250',
        15 => '200x200',
        16 => '180x150',
        17 => '125x125',
        #regional
        18 => '240x400',# Most popular size in Russia.
        19 => '980x120', # Most popular size in Sweden and Finland. Can also be used as a substitute in Norway.
        20 => '250x360', # Second most popular size in Sweden.
        21 => '930x180', # Very popular size in Denmark.
        22 => '580x400', # Very popular size in Norway.
        #polish
        23 => '750x100', # Very popular size in Poland.
        24 => '750x200', # Most popular size in Poland.
        25 => '750x300', # Third most popular size in Poland.
        # https://en.wikipedia.org/wiki/Web_banner
        26 => '300x100',
        27 => '120x90',
        28 => '120x60',
        29 => '88x31',
    ];

    public const TYPE_DISPLAY = 'display';

    public const TYPES = [
        self::TYPE_DISPLAY,
    ];

    public const SIZE_INFOS = [
        #best
        '300x250' => [
            'label' => 'medium-rectangle',
            'tags' => ['Desktop', 'best'],
            'type' => self::TYPE_DISPLAY,
        ],
        '336x280' => [
            'label' => 'large-rectangle',
            'tags' => ['Desktop', 'best'],
            'type' => self::TYPE_DISPLAY,
        ],
        '728x90' => [
            'label' => 'leaderboard',
            'tags' => ['Desktop', 'best'],
            'type' => self::TYPE_DISPLAY,
        ],
        '300x600' => [
            'label' => 'half-page',
            'tags' => ['Desktop', 'best'],
            'type' => self::TYPE_DISPLAY,
        ],
        '320x100' => [
            'label' => 'large-mobile-banner',
            'tags' => ['Desktop', 'best', 'Mobile'],
            'type' => self::TYPE_DISPLAY,
        ],
        #other
        '320x50' => [
            'label' => 'mobile-banner',
            'tags' => ['Desktop', 'Mobile'],
            'type' => self::TYPE_DISPLAY,
        ],
        '468x60' => [
            'label' => 'full-banner',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '234x60' => [
            'label' => 'half-banner',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '120x600' => [
            'label' => 'skyscraper',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '120x240' => [
            'label' => 'vertical-banner',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '160x600' => [
            'label' => 'wide-skyscraper',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '300x1050' => [
            'label' => 'portrait',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '970x90' => [
            'label' => 'large-leaderboard',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '970x250' => [
            'label' => 'billboard',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '250x250' => [
            'label' => 'square',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '200x200' => [
            'label' => 'small-square',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '180x150' => [
            'label' => 'small-rectangle',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '125x125' => [
            'label' => 'button',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        #regional
        '240x400' => [
            'label' => 'vertical-rectangle',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '980x120' => [
            'label' => 'panorama',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '250x360' => [
            'label' => 'triple-widescreen',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '930x180' => [
            'label' => 'top-banner',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '580x400' => [
            'label' => 'netboard',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        #polish
        '750x100' => [
            'label' => 'single-billboard',
            'tags' => ['Desktop', 'PL'],
            'type' => self::TYPE_DISPLAY,
        ],
        '750x200' => [
            'label' => 'double-billboard',
            'tags' => ['Desktop', 'PL'],
            'type' => self::TYPE_DISPLAY,
        ],
        '750x300' => [
            'label' => 'triple-billboard',
            'tags' => ['Desktop', 'PL'],
            'type' => self::TYPE_DISPLAY,
        ],
        # https://en.wikipedia.org/wiki/Web_banner
        '300x100' => [
            'label' => '3-to-1-rectangle',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '120x90' => [
            'label' => 'button-one',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '120x60' => [
            'label' => 'button-two',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
        '88x31' => [
            'label' => 'micro-banner',
            'tags' => ['Desktop'],
            'type' => self::TYPE_DISPLAY,
        ],
    ];

    public static function isValid(string $size): bool
    {
        return in_array($size, self::SUPPORTED_SIZES);
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
}
