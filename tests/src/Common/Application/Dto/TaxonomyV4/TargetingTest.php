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

use Adshares\Common\Application\Dto\TaxonomyV4\Targeting;
use Adshares\Common\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TargetingTest extends TestCase
{
    public function testTargetingItemFromArray(): void
    {
        $targeting = Targeting::fromArray(self::data());

        $arr = $targeting->toArray();
        self::assertEmpty($arr['user']);
        self::assertEquals('input', $arr['site'][0]['type'] ?? null);
        self::assertEquals('MetaMask', $arr['device'][0]['items']['metamask'] ?? null);
    }

    /**
     * @dataProvider keyProvider
     */
    public function testTargetingFromArrayMissingField($remove): void
    {
        self::expectException(InvalidArgumentException::class);
        Targeting::fromArray(self::data([], $remove));
    }

    /**
     * @dataProvider keyProvider
     */
    public function testTargetingFromArrayInvalidFieldType($field): void
    {
        self::expectException(InvalidArgumentException::class);
        Targeting::fromArray(self::data([$field => 0]));
    }

    private static function data(array $mergeData = [], string $remove = null): array
    {
        $data = array_merge([
            'user' => [],
            'site' => [
                [
                    'type' => 'input',
                    'name' => 'domain',
                    'label' => 'Domain'
                ]
            ],
            'device' => [
                [
                    'type' => 'dict',
                    'name' => 'extensions',
                    'label' => 'Extensions',
                    'items' => [
                        'metamask' => 'MetaMask'
                    ]
                ]
            ]
        ], $mergeData);

        if ($remove !== null) {
            unset($data[$remove]);
        }

        return $data;
    }

    public function keyProvider(): array
    {
        return [
            ['user'],
            ['site'],
            ['device'],
        ];
    }
}
