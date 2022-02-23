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

namespace Adshares\Tests\Common\Application\Dto;

use Adshares\Common\Application\Dto\TaxonomyV4;
use Adshares\Common\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TaxonomyV4Test extends TestCase
{
    public function testTaxonomyFromArray(): void
    {
        $targeting = TaxonomyV4::fromArray(self::data());

        $arr = $targeting->toArray();
        self::assertEquals('web', $arr['name'] ?? null);
        self::assertEquals('Medium Rectangle', $arr['formats'][0]['scopes']['300x250'] ?? null);
        self::assertEquals('MetaMask', $arr['targeting']['device'][0]['items']['metamask'] ?? null);
    }

    /**
     * @dataProvider keyProvider
     */
    public function testTaxonomyFromArrayMissingField($remove): void
    {
        self::expectException(InvalidArgumentException::class);
        TaxonomyV4::fromArray(self::data([], $remove));
    }

    /**
     * @dataProvider keyProvider
     */
    public function testTaxonomyFromArrayInvalidFieldType($field): void
    {
        self::expectException(InvalidArgumentException::class);
        TaxonomyV4::fromArray(self::data([$field => 0]));
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
            ['meta'],
            ['media'],
        ];
    }
}
