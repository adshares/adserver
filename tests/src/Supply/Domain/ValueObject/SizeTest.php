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

namespace Adshares\Tests\Supply\Domain\ValueObject;

use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Supply\Domain\ValueObject\Size;
use PHPUnit\Framework\TestCase;

final class SizeTest extends TestCase
{
    public function testDimensions(): void
    {
        $this->assertEquals('728x90', Size::fromDimensions(728, 90));
        $this->assertEquals('1x0', Size::fromDimensions(1, 0));
        $this->assertEquals([728, 90], Size::toDimensions('728x90'));
        $this->assertEquals([0, 90], Size::toDimensions('x90'));
        $this->assertEquals([728, 0], Size::toDimensions('728'));
        $this->assertEquals([0, 0], Size::toDimensions(''));
    }

    public function testAspect(): void
    {
        $this->assertEquals('4:3', Size::getAspect(320, 240));
        $this->assertEquals('6:5', Size::getAspect(300, 250));
        $this->assertEquals('', Size::getAspect(320, 0));
        $this->assertEquals('', Size::getAspect(0, 240));
    }

    public function testFindBestFit(): void
    {
        $medium = Medium::fromArray([
            'name' => 'web',
            'label' => 'Website',
            'formats' => [
                [
                    'type' => 'image',
                    'mimes' => ['image/png'],
                    'scopes' => [
                        '300x250' => 'Medium Rectangle',
                        '728x90' => 'Leaderboard',
                        '300x600' => 'Half Page',
                        '320x100' => 'Large Mobile Banner',
                    ],
                ],
                [
                    'type' => 'video',
                    'mimes' => ['video/mp4'],
                    'scopes' => [
                        '300x250' => 'Medium Rectangle',
                        '336x280' => 'Large Rectangle',
                    ],
                ]
            ],
            'targeting' => [
                'user' => [],
                'site' => [],
                'device' => [],
            ],
        ]);

        $this->assertContains('300x250', Size::findBestFit($medium, 300, 250, 0, 1));
        $this->assertContains('336x280', Size::findBestFit($medium, 330, 270, 0, 1));
        $this->assertContains('cube', Size::findBestFit($medium, 330, 270, 10, 1));
    }

    public function testFindMatching(): void
    {
        $sizes = array_keys(Size::SIZE_INFOS);

        $this->assertEmpty(Size::findMatchingWithSizes($sizes, 1, 1));
        $this->assertEmpty(Size::findMatchingWithSizes($sizes, 300, 0));
        $this->assertEmpty(Size::findMatchingWithSizes($sizes, 300, 10));
        $this->assertEmpty(Size::findMatchingWithSizes($sizes, 3000, 4000));
        $this->assertEmpty(Size::findMatchingWithSizes($sizes, 4000, 3000));
        $this->assertContains('300x250', Size::findMatchingWithSizes($sizes, 300, 250));
        $this->assertContains('300x250', Size::findMatchingWithSizes($sizes, 320, 240));
        $this->assertContains('580x400', Size::findMatchingWithSizes($sizes, 1920, 1080));
        $this->assertContains('300x600', Size::findMatchingWithSizes($sizes, 1080, 1920));
    }
}
