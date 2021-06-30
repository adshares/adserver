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

namespace Adshares\Tests\Common\Application\Dto\TaxonomyVersion0;

use Adshares\Common\Domain\ValueObject\SemVer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SemVerTest extends TestCase
{

    /**
     * @dataProvider successProvider
     *
     * @param string $input
     * @param string $expectation
     */
    public function testSuccess(string $input, string $expectation): void
    {
        $semVer = SemVer::fromString($input);

        self::assertSame($expectation, (string)$semVer);
    }

    public function successProvider(): array
    {
        return [
            ['1.2.3', '1.2.3'],
            ['10.20.30', '10.20.30'],
            ['0', '0.0.0'],
            ['0.0', '0.0.0'],
            ['0.0.0', '0.0.0'],
            ['1', '1.0.0'],
            ['2.0', '2.0.0'],
            ['3.0.0', '3.0.0'],
            ['v0', '0.0.0'],
            ['v0.0', '0.0.0'],
            ['v0.0.0', '0.0.0'],
            ['release-0', '0.0.0'],
            ['release-0.0', '0.0.0'],
            ['release-0.0.0', '0.0.0'],
        ];
    }

    /**
     * @dataProvider failureProvider
     *
     * @param string $input
     */
    public function testFailure(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        SemVer::fromString($input);
    }

    public function failureProvider(): array
    {
        return [
            [''],
            [' '],
            ['...'],
            ['v'],
            ['x'],
            ['0.x'],
            ['0.0.x'],
            ['06.6.6'],
            ['6.06.6'],
            ['6.6.06'],
            [' .6.6'],
            ['6. .6'],
            ['6.6. '],
            ['v-3.2.1'],
            ['version-3.2.1'],
            ['3-a.2.1'],
            ['3.2-b.1'],
            ['3.2.1-c'],
        ];
    }
}
