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

namespace Adshares\Tests\Supply\Domain\ValueObject;

use Adshares\Supply\Domain\Model\Exception\UnsupportedStatusTypeException;
use Adshares\Supply\Domain\ValueObject\Status;
use PHPUnit\Framework\TestCase;

final class StatusTest extends TestCase
{
    public function testStatus(): void
    {
        self::assertEquals(Status::STATUS_PROCESSING, Status::processing()->getStatus());
        self::assertEquals(Status::STATUS_ACTIVE, Status::active()->getStatus());
        self::assertEquals(Status::STATUS_TO_DELETE, Status::toDelete()->getStatus());
        self::assertEquals(Status::STATUS_DELETED, Status::deleted()->getStatus());
    }

    /**
     * @dataProvider statusProvider
     * @param int $status
     */
    public function testFromValid(int $status): void
    {
        self::assertEquals($status, Status::fromStatus($status)->getStatus());
    }

    public function testFromInvalid(): void
    {
        self::expectException(UnsupportedStatusTypeException::class);

        Status::fromStatus(-1);
    }

    public function statusProvider(): array
    {
        return [[0], [1], [2], [3]];
    }
}
