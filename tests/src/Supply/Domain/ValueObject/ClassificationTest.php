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

namespace Adshares\Tests\Supply\Domain\ValueObject;

use Adshares\Supply\Domain\ValueObject\Classification;
use PHPUnit\Framework\TestCase;

final class ClassificationTest extends TestCase
{
    public function testClassificationEquality(): void
    {
        $classification1 = new Classification('classify', ['1:1:1']);
        $classification2 = new Classification('classify', ['1:1:1']);
        $classification3 = new Classification('classify', ['1:1:2']);

        $this->assertTrue($classification1->equals($classification2));
        $this->assertTrue($classification2->equals($classification1));

        $this->assertFalse($classification1->equals($classification3));
        $this->assertFalse($classification2->equals($classification3));
    }

    public function testClassificationToArray(): void
    {
        $classification1 = new Classification('classify', ['1:1:1']);

        $expected = [
            'classify' => ['1:1:1'],
        ];

        $this->assertEquals($expected, $classification1->toArray());
    }
}
