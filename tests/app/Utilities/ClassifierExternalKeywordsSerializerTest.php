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

namespace Adshares\Adserver\Tests\Utilities;

use Adshares\Adserver\Utilities\ClassifierExternalKeywordsSerializer;
use PHPUnit\Framework\TestCase;

final class ClassifierExternalKeywordsSerializerTest extends TestCase
{
    /**
     * @dataProvider keywordsProvider
     *
     * @param array $keywords
     */
    public function testSerialization(array $keywords): void
    {
        $expectedSerialized = '{"category":["crypto","gambling"],"type":"image"}';
        $serialized = ClassifierExternalKeywordsSerializer::serialize($keywords);

        $this->assertEquals($expectedSerialized, $serialized);
    }

    public function testSerializationEmpty(): void
    {
        $expectedSerialized = '{"category":[],"type":"image"}';
        $keywords = [
            'category' => [],
            'type' => 'image',
        ];
        $serialized = ClassifierExternalKeywordsSerializer::serialize($keywords);

        $this->assertEquals($expectedSerialized, $serialized);
    }

    public function keywordsProvider(): array
    {
        return [
            [
                'keywords' => [
                    'category' => ['crypto', 'gambling'],
                    'type' => 'image',
                ],
            ],
            [
                'keywords' => [
                    'type' => 'image',
                    'category' => ['crypto', 'gambling'],
                ],
            ],
            [
                'keywords' => [
                    'category' => ['gambling', 'crypto'],
                    'type' => 'image',
                ],
            ],
            [
                'keywords' => [
                    'type' => 'image',
                    'category' => ['gambling', 'crypto'],
                ],
            ],
        ];
    }
}
