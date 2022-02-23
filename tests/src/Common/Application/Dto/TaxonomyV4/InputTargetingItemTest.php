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

use Adshares\Common\Application\Dto\TaxonomyV4\InputTargetingItem;
use PHPUnit\Framework\TestCase;

class InputTargetingItemTest extends TestCase
{
    public function testInputTargeting(): void
    {
        $item = new InputTargetingItem('domain', 'Domain');

        $arr = $item->toArray();
        self::assertEquals('input', $arr['type']);
        self::assertEquals('domain', $arr['name']);
        self::assertEquals('Domain', $arr['label']);
    }
}
