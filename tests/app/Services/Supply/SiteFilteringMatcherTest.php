<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Services\Supply;

use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Services\Supply\SiteFilteringMatcher;
use Adshares\Adserver\Tests\TestCase;

class SiteFilteringMatcherTest extends TestCase
{
    public function testEmptyFiltering(): void
    {
        $site = self::createSite();

        $this->assertTrue(SiteFilteringMatcher::checkClassification($site, []));
        $this->assertTrue(SiteFilteringMatcher::checkClassification($site, ['foo' => ['dummy']]));
        $this->assertTrue(SiteFilteringMatcher::checkClassification($site, self::getClassification()));
        $this->assertTrue(SiteFilteringMatcher::checkClassification(new Site(), self::getClassification()));
    }

    public function testEmptyClassification(): void
    {
        $this->assertTrue(SiteFilteringMatcher::checkClassification(new Site(), []));
        $this->assertTrue(SiteFilteringMatcher::checkClassification(self::createSite(), []));
        $this->assertTrue(SiteFilteringMatcher::checkClassification(self::createSite([], ['foo' => ['dummy']]), []));

        $this->assertFalse(SiteFilteringMatcher::checkClassification(self::createSite(['foo' => ['dummy']]), []));
        $this->assertFalse(
            SiteFilteringMatcher::checkClassification(self::createSite(['foo' => ['dummy']], ['ext' => ['123']]), [])
        );
    }

    public function testFiltering(): void
    {
        $this->assertTrue(
            SiteFilteringMatcher::checkClassification(
                self::createSite(
                    [
                        '0001:quality' => ['high', 'low'],
                        '0001:classified' => ['1'],
                    ],
                    [
                        'classify' => ['555:0'],
                        '0001:category' => ['adult', 'annoying']
                    ]
                ),
                self::getClassification()
            )
        );
        $this->assertTrue(
            SiteFilteringMatcher::checkClassification(
                self::createSite(
                    [
                        '0001:quality' => ['high', 'low'],
                        '0001:classified' => ['1'],
                    ],
                    []
                ),
                self::getClassification()
            )
        );
        $this->assertTrue(
            SiteFilteringMatcher::checkClassification(
                self::createSite(
                    [],
                    [
                        'classify' => ['555:0'],
                        '0001:category' => ['adult', 'annoying']
                    ]
                ),
                self::getClassification()
            )
        );

        $this->assertFalse(
            SiteFilteringMatcher::checkClassification(
                self::createSite(
                    [
                        '0001:quality' => ['high', 'low'],
                        '0001:classified' => ['1'],
                    ],
                    [
                        'classify' => ['555:0'],
                        '0001:category' => ['adult', 'annoying', 'crypto']
                    ]
                ),
                self::getClassification()
            )
        );
        $this->assertFalse(
            SiteFilteringMatcher::checkClassification(
                self::createSite(
                    [
                        '0001:quality' => ['medium', 'low'],
                        '0001:classified' => ['1'],
                    ],
                    [
                        'classify' => ['555:0'],
                        '0001:category' => ['adult', 'annoying']
                    ]
                ),
                self::getClassification()
            )
        );
        $this->assertFalse(
            SiteFilteringMatcher::checkClassification(
                self::createSite(
                    [
                        '0001:category' => ['finance']
                    ],
                    [
                        '0001:category' => ['crypto']
                    ]
                ),
                self::getClassification()
            )
        );
        $this->assertTrue(
            SiteFilteringMatcher::checkClassification(
                self::createSite(
                    [
                        '0001:category' => ['finance', 'foo', 'dummy']
                    ]
                ),
                self::getClassification()
            )
        );
        $this->assertFalse(
            SiteFilteringMatcher::checkClassification(
                self::createSite(
                    [],
                    [
                        '0001:category' => ['finance', 'foo', 'dummy']
                    ]
                ),
                self::getClassification()
            )
        );

        $this->assertFalse(
            SiteFilteringMatcher::checkClassification(
                self::createSite(
                    [
                        '0002:quality' => ['high'],
                    ],
                    [
                        '0001:category' => ['adult']
                    ]
                ),
                self::getClassification()
            )
        );
        $this->assertTrue(
            SiteFilteringMatcher::checkClassification(
                self::createSite(
                    [
                        '0001:quality' => ['high'],
                    ],
                    [
                        '0002:category' => ['adult']
                    ]
                ),
                self::getClassification()
            )
        );
    }

    private static function createSite(array $requires = [], array $excludes = []): Site
    {
        $site = new Site();
        $site->site_requires = $requires;
        $site->site_excludes = $excludes;
        return $site;
    }

    private static function getClassification(): array
    {
        return [
            'classify' => [
                '111:1',
                '222:1',
                '333:0',
            ],
            '0001' => [
                'category' => [
                    'crypto',
                    'finance',
                ],
                'quality' => [
                    'high',
                ],
                'classified' => [
                    '1',
                ]
            ]
        ];
    }
}
