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

namespace Adshares\Tests\Common\Application\Factory;

use Adshares\Common\Application\Dto\TaxonomyV4;
use Adshares\Common\Application\Factory\TaxonomyV4Factory;
use PHPUnit\Framework\TestCase;

class TaxonomyV4FactoryTest extends TestCase
{
    public function testTaxonomyFromJson(): void
    {
        $taxonomy = TaxonomyV4Factory::fromJson(self::jsonTaxonomy());
        self::assertInstanceOf(TaxonomyV4::class, $taxonomy);
    }

    private static function jsonTaxonomy(): string
    {
        return file_get_contents('tests/mock/targeting_schema_v4.json');
    }
}
