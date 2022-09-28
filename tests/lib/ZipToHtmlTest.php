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

namespace Adshares\Lib\Tests;

use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Lib\ZipToHtml;

class ZipToHtmlTest extends TestCase
{
    /**
     * @dataProvider invalidFilesProvider
     */
    public function testInvalidFile(string $filename): void
    {
        $path = base_path('tests/mock/Files/Banners/' . $filename);

        self::expectException(RuntimeException::class);

        (new ZipToHtml($path))->getHtml();
    }

    public function invalidFilesProvider(): array
    {
        return [
            'empty' => ['empty.zip'],
            'two html files' => ['2xhtml.zip'],
            'too big unzipped' => ['too_big_unzipped.zip'],
            'too big zipped' => ['too_big_zipped.zip'],
        ];
    }
}
