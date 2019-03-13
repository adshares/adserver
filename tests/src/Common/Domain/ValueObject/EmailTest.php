<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

declare(strict_types = 1);

namespace Adshares\Tests\Common\Domain\ValueObject;

use Adshares\Common\Domain\ValueObject\Email;
use Adshares\Common\Exception\RuntimeException;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    /** @dataProvider emailProvider */
    public function testEmail(string $email, bool $expectedException = false): void
    {
        if ($expectedException) {
            $this->expectException(RuntimeException::class);
        }

        $object = new Email($email);

        self::assertSame($email, $object->toString());
    }

    public function emailProvider(): array
    {
        return [
            ['test@example.com'],
            ['test2.example@example.com.pl'],
            ['', true],
            ['example.pl', true],
        ];
    }
}
