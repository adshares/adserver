<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Test\Supply\Domain\ValueObject;

use Adshares\Supply\Domain\ValueObject\Exception\InvalidUrlException;
use Adshares\Supply\Domain\ValueObject\SourceHost;
use PHPUnit\Framework\TestCase;

final class SourceHostTest extends TestCase
{
    public function testWhenHostIsInvalid(): void
    {
        $this->expectException(InvalidUrlException::class);

        new SourceHost('', '0001-00000001-0001', '0.1');
    }
}
