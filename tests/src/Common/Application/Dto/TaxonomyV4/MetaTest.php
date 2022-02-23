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

use Adshares\Common\Application\Dto\TaxonomyV4\Meta;
use Adshares\Common\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MetaTest extends TestCase
{
    public function testMetaFromArray(): void
    {
        $targeting = Meta::fromArray(self::data());

        $arr = $targeting->toArray();
        self::assertEquals('simple', $arr['name'] ?? null);
        self::assertEquals('4.0.0', $arr['version'] ?? null);
    }

    /**
     * @dataProvider keyProvider
     */
    public function testMetaFromArrayMissingField($remove): void
    {
        self::expectException(InvalidArgumentException::class);
        Meta::fromArray(self::data([], $remove));
    }

    /**
     * @dataProvider keyProvider
     */
    public function testMetaFromArrayInvalidFieldType($field): void
    {
        self::expectException(InvalidArgumentException::class);
        Meta::fromArray(self::data([$field => 0]));
    }

    private static function data(array $mergeData = [], string $remove = null): array
    {
        $data = array_merge([
            'name' => 'simple',
            'version' => '4.0.0',
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
            ['version'],
        ];
    }
}
