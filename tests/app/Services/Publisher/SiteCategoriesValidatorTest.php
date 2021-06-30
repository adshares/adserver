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

namespace Adshares\Adserver\Tests\Services\Publisher;

use Adshares\Adserver\Services\Publisher\SiteCategoriesValidator;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Exception\InvalidArgumentException;
use Adshares\Mock\Repository\DummyConfigurationRepository;

final class SiteCategoriesValidatorTest extends TestCase
{
    /**
     * @dataProvider validCategoriesProvider
     *
     * @param array $categories
     */
    public function testValidCategories(array $categories): void
    {
        $siteCategoriesValidator = new SiteCategoriesValidator(new DummyConfigurationRepository());

        $result = $siteCategoriesValidator->processCategories($categories);

        self::assertTrue(is_array($result));
    }

    public function validCategoriesProvider(): array
    {
        return [
            [['unknown']],
            [['adult', 'health']],
        ];
    }

    /**
     * @dataProvider invalidCategoriesProvider
     *
     * @param mixed $categories
     */
    public function testInvalidCategories($categories): void
    {
        $siteCategoriesValidator = new SiteCategoriesValidator(new DummyConfigurationRepository());

        self::expectException(InvalidArgumentException::class);

        $siteCategoriesValidator->processCategories($categories);
    }

    public function invalidCategoriesProvider(): array
    {
        return [
            [null],
            ['unknown'],
            [[]],
            [['0']],
        ];
    }
}
