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

use Adshares\Common\Application\Dto\TaxonomyV2\DictionaryTargetingItem;
use PHPUnit\Framework\TestCase;

class DictionaryTargetingItemTest extends TestCase
{
    public function testDictionaryItem(): void
    {
        $item = new DictionaryTargetingItem('category', 'Category', ['paytoclick' => 'Pay to Click']);

        $arr = $item->toArray();
        self::assertEquals('dict', $arr['type']);
        self::assertEquals('category', $arr['name']);
        self::assertEquals('Category', $arr['label']);
        self::assertEquals(['paytoclick' => 'Pay to Click'], $arr['items']);
    }
}
