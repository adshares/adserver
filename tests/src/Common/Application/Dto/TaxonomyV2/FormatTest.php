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

namespace Adshares\Tests\Common\Application\Dto\TaxonomyV2;

use Adshares\Common\Application\Dto\TaxonomyV2\Format;
use Adshares\Common\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase
{
    public function testFormatFromArray(): void
    {
        $format = Format::fromArray(self::data());

        $arr = $format->toArray();
        self::assertEquals('image', $arr['type']);
        self::assertContains('image/gif', $arr['mimes']);
        $scopes = $arr['scopes'];
        self::assertContains('300x250', array_keys($scopes));
        self::assertContains('Medium Rectangle', $scopes['300x250']);
    }

    /**
     * @dataProvider keyProvider
     */
    public function testFormatFromArrayMissingField($remove): void
    {
        self::expectException(InvalidArgumentException::class);
        Format::fromArray(self::data([], $remove));
    }

    public function testFormatFromArrayInvalidType(): void
    {
        self::expectException(InvalidArgumentException::class);
        Format::fromArray(self::data(['type' => 0]));
    }

    public function testFormatFromArrayInvalidMimes(): void
    {
        self::expectException(InvalidArgumentException::class);
        Format::fromArray(self::data(['mimes' => 'image/png']));
    }

    public function testFormatFromArrayInvalidMimesValues(): void
    {
        self::expectException(InvalidArgumentException::class);
        Format::fromArray(self::data(['mimes' => [0, 1]]));
    }

    public function testFormatFromArrayEmptyMimesArray(): void
    {
        self::expectException(InvalidArgumentException::class);
        Format::fromArray(self::data(['mimes' => []]));
    }

    public function testFormatFromArrayInvalidScopes(): void
    {
        self::expectException(InvalidArgumentException::class);
        Format::fromArray(self::data(['scopes' => '300x250']));
    }

    public function testFormatFromArrayInvalidScopesKey(): void
    {
        self::expectException(InvalidArgumentException::class);
        Format::fromArray(self::data(['scopes' => ['300x250']]));
    }

    public function testFormatFromArrayInvalidScopesLabel(): void
    {
        self::expectException(InvalidArgumentException::class);
        Format::fromArray(self::data(['scopes' => ['300x250' => 1]]));
    }

    public function testFormatFromArrayEmptyScopesArray(): void
    {
        self::expectException(InvalidArgumentException::class);
        Format::fromArray(self::data(['scopes' => []]));
    }

    private static function data(array $mergeData = [], string $remove = null): array
    {
        $data = array_merge([
            'type' => 'image',
            'mimes' => ['image/gif', 'image/jpeg'],
            'scopes' => [
                '300x250' => 'Medium Rectangle',
                '336x280' => 'Large Rectangle',
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
            ['type'],
            ['mimes'],
            ['scopes'],
        ];
    }
}
