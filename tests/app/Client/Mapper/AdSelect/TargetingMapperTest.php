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

namespace Adshares\Adserver\Tests\Client\Mapper\AdSelect;

use Adshares\Adserver\Client\Mapper\AdSelect\TargetingMapper;
use PHPUnit\Framework\TestCase;
use stdClass;

final class TargetingMapperTest extends TestCase
{
    public function testWhenTargetingIsEmpty(): void
    {
        $mapped = TargetingMapper::map(
            [],
            []
        );

        $this->assertEquals(new stdClass(), $mapped['require']);
        $this->assertEquals(new stdClass(), $mapped['exclude']);
    }

    public function testWhenTargetingIsNotEmpty()
    {
        $requires = [
            'site' => [
                'lang' => [
                    'en',
                    'pl',
                ],
                'title' => [
                    'subtitle' => [
                        'title-subtitle',
                    ],
                ],
            ],
            'user' => [
                'gender' => [
                    'male'
                ],
            ],
            'device' => [
                'os' => [
                    'Linux',
                    'Windows',
                    'Apple_OS',
                ]
            ]
        ];

        $excludes = [
            'site' => [
                'domain' => [
                    'domain1.example.com',
                    'domain2.example.com',
                ],
            ],
            'user' => [
                'lang' => [
                    'it',
                ],
            ],
        ];

        $mapped = TargetingMapper::map(
            $requires,
            $excludes
        );

        $expectedRequires = [
            'site:lang' => ['en', 'pl'],
            'site:title:subtitle' => ['title-subtitle'],
            'user:gender' => ['male'],
            'device:os' => ['Linux', 'Windows','Apple_OS'],
        ];

        $expectedExcludes = [
          'site:domain' => ['domain1.example.com', 'domain2.example.com'],
          'user:lang' => ['it'],
        ];

        $this->assertEquals($expectedExcludes, $mapped['exclude']);
        $this->assertEquals($expectedRequires, $mapped['require']);
    }
}
