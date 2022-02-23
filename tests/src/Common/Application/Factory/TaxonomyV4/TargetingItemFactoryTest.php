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

namespace Adshares\Tests\Common\Application\Factory\TaxonomyV4;

use Adshares\Common\Application\Dto\TaxonomyV3\DictionaryTargetingItem;
use Adshares\Common\Application\Dto\TaxonomyV3\InputTargetingItem;
use Adshares\Common\Application\Factory\TaxonomyV4\TargetingItemFactory;
use Adshares\Common\Exception\InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TargetingItemFactoryTest extends TestCase
{
    public function testInputTargetingItemFromArray(): void
    {
        $targetingItem = TargetingItemFactory::fromArray(self::inputData());
        self::assertInstanceOf(InputTargetingItem::class, $targetingItem);
    }

    public function testDictionaryTargetingItemFromArray(): void
    {
        $targetingItem = TargetingItemFactory::fromArray(self::dictionaryData());
        self::assertInstanceOf(DictionaryTargetingItem::class, $targetingItem);
    }

    /**
     * @dataProvider keyProvider
     */
    public function testInputTargetingItemFromArrayMissingField($remove): void
    {
        self::expectException(InvalidArgumentException::class);
        TargetingItemFactory::fromArray(self::inputData([], $remove));
    }

    /**
     * @dataProvider keyProvider
     */
    public function testInputTargetingItemFromArrayInvalidFieldType($field): void
    {
        self::expectException(InvalidArgumentException::class);
        TargetingItemFactory::fromArray(self::inputData([$field => 0]));
    }

    public function testDictionaryTargetingItemFromArrayMissingItems(): void
    {
        self::expectException(InvalidArgumentException::class);
        TargetingItemFactory::fromArray(self::dictionaryData([], 'items'));
    }

    public function testDictionaryTargetingItemFromArrayInvalidItems(): void
    {
        self::expectException(InvalidArgumentException::class);
        TargetingItemFactory::fromArray(self::dictionaryData(['items' => 0]));
    }

    public function testDictionaryTargetingItemFromArrayEmptyItemsArray(): void
    {
        self::expectException(InvalidArgumentException::class);
        TargetingItemFactory::fromArray(self::dictionaryData(['items' => []]));
    }

    public function testDictionaryTargetingItemFromArrayInvalidItemsKey(): void
    {
        self::expectException(InvalidArgumentException::class);
        TargetingItemFactory::fromArray(self::dictionaryData(['items' => ['adult', 'crypto']]));
    }

    public function testDictionaryTargetingItemFromArrayInvalidItemsValue(): void
    {
        self::expectException(InvalidArgumentException::class);
        TargetingItemFactory::fromArray(self::dictionaryData(['items' => ['adult' => 1]]));
    }

    public function testDictionaryTargetingItemFromArrayNestedItemsMissingLabel(): void
    {
        self::expectException(InvalidArgumentException::class);
        TargetingItemFactory::fromArray(
            self::dictionaryData(
                ['items' => ['health' => self::nestedItem([], 'label')]]
            )
        );
    }

    public function testDictionaryTargetingItemFromArrayNestedItemsMissingValues(): void
    {
        self::expectException(InvalidArgumentException::class);
        TargetingItemFactory::fromArray(
            self::dictionaryData(
                ['items' => ['health' => self::nestedItem([], 'values')]]
            )
        );
    }

    public function testDictionaryTargetingItemFromArrayNestedValuesInvalidLabel(): void
    {
        self::expectException(InvalidArgumentException::class);
        TargetingItemFactory::fromArray(
            self::dictionaryData(
                ['items' => ['health' => self::nestedItem(['label' => 1])]]
            )
        );
    }

    public function testDictionaryTargetingItemFromArrayNestedValuesInvalidValues(): void
    {
        self::expectException(InvalidArgumentException::class);
        TargetingItemFactory::fromArray(
            self::dictionaryData(
                ['items' => ['health' => self::nestedItem(['values' => 1])]]
            )
        );
    }

    public function testDictionaryTargetingItemFromArrayNestedValuesEmpty(): void
    {
        self::expectException(InvalidArgumentException::class);
        TargetingItemFactory::fromArray(
            self::dictionaryData(
                ['items' => ['health' => self::nestedItem(['values' => []])]]
            )
        );
    }

    public function testDictionaryTargetingItemFromArrayNestedValuesInvalidKey(): void
    {
        self::expectException(InvalidArgumentException::class);
        TargetingItemFactory::fromArray(
            self::dictionaryData(
                ['items' => ['health' => self::nestedItem(['values' => ['test']])]]
            )
        );
    }

    public function testInvalidTargetingItemFromArray(): void
    {
        self::expectException(InvalidArgumentException::class);
        TargetingItemFactory::fromArray(self::inputData(['type' => 'invalid']));
    }

    private static function inputData(array $mergeData = [], string $remove = null): array
    {
        $data = array_merge([
            'type' => 'input',
            'name' => 'domain',
            'label' => 'Domain',
        ], $mergeData);

        if ($remove !== null) {
            unset($data[$remove]);
        }

        return $data;
    }

    private static function dictionaryData(array $mergeData = [], string $remove = null): array
    {
        $data = array_merge([
            'type' => 'dict',
            'name' => 'category',
            'label' => 'Category',
            'items' => [
                'adult' => 'Adult',
                'crypto' => 'Crypto',
                'faucets' => 'Faucets',
                'games' => 'Games',
                'health' => self::nestedItem(),
                'lifestyle' => 'Lifestyle',
                'movies' => 'Movies',
                'music' => 'Music',
                'news' => 'News',
                'paytoclick' => 'Pay to Click',
                'technology' => 'Technology',
                'unknown' => 'Unknown'
            ],
        ], $mergeData);

        if ($remove !== null) {
            unset($data[$remove]);
        }

        return $data;
    }

    private static function nestedItem(array $mergeData = [], string $remove = null): array
    {
        $data = array_merge([
            'label' => 'Health',
            'values' => [
                'supplements' => 'Supplements',
                'diets' => 'Diets'
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
            ['type'],
            ['name'],
            ['label'],
        ];
    }
}
