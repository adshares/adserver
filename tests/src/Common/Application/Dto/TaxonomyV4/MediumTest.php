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

namespace Adshares\Tests\Common\Application\Dto\TaxonomyV4;

use Adshares\Common\Application\Dto\TaxonomyV4\Medium;
use Adshares\Common\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MediumTest extends TestCase
{
    public function testMediumFromArray(): void
    {
        $targeting = Medium::fromArray(self::data());

        $arr = $targeting->toArray();
        self::assertEquals('web', $arr['name'] ?? null);
        self::assertEquals('Medium Rectangle', $arr['formats'][0]['scopes']['300x250'] ?? null);
        self::assertEquals('MetaMask', $arr['targeting']['device'][0]['items']['metamask'] ?? null);
    }

    /**
     * @dataProvider keyProvider
     */
    public function testMediumFromArrayMissingField($remove): void
    {
        self::expectException(InvalidArgumentException::class);
        Medium::fromArray(self::data([], $remove));
    }

    /**
     * @dataProvider keyProvider
     */
    public function testMediumFromArrayInvalidFieldType($field): void
    {
        self::expectException(InvalidArgumentException::class);
        Medium::fromArray(self::data([$field => 0]));
    }

    public function testMediumFromArrayEmptyFormatsArray(): void
    {
        self::expectException(InvalidArgumentException::class);
        Medium::fromArray(self::data(['formats' => []]));
    }

    private static function data(array $mergeData = [], string $remove = null): array
    {
        $data = array_merge([
            'name' => 'web',
            'label' => 'Website',
            'formats' => [
                [
                    'type' => 'image',
                    'mimes' => ['image/png'],
                    'scopes' => [
                        '300x250' => 'Medium Rectangle',
                    ],
                ],
            ],
            'targeting' => [
                'user' => [],
                'site' => [],
                'device' => [
                    [
                        'type' => 'dict',
                        'name' => 'extensions',
                        'label' => 'Extensions',
                        'items' => [
                            'metamask' => 'MetaMask'
                        ],
                    ],
                ],
            ],
        ], $mergeData);

        if ($remove !== null) {
            unset($data[$remove]);
        }

        return $data;
    }

    public function keyProvider(): array
    {
        return [
            ['name'],
            ['label'],
            ['formats'],
            ['targeting'],
        ];
    }
}
