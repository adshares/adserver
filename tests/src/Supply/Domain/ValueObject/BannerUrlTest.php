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

namespace Adshares\Test\Supply\Domain\ValueObject;

use Adshares\Supply\Domain\ValueObject\BannerUrl;
use Adshares\Supply\Domain\ValueObject\Exception\InvalidUrlException;
use PHPUnit\Framework\TestCase;

final class BannerUrlTest extends TestCase
{
    private const VALID = true;
    private const INVALID = false;

    /**
     * @dataProvider dataProvider
     *
     * @param $serveUrl
     * @param $clickUrl
     * @param $viewUrl
     * @param bool $valid
     */
    public function testIfBannerUrlIsValid($serveUrl, $clickUrl, $viewUrl, bool $valid = self::VALID): void
    {
        if (!$valid) {
            $this->expectException(InvalidUrlException::class);
        }

        $bannerUrl = new BannerUrl($serveUrl, $clickUrl, $viewUrl);

        $this->assertEquals($serveUrl, $bannerUrl->getServeUrl());
        $this->assertEquals($clickUrl, $bannerUrl->getClickUrl());
        $this->assertEquals($viewUrl, $bannerUrl->getViewUrl());
    }

    public function dataProvider()
    {
        return [
            ['http://example.com', 'http://example.com', 'http://example.com', self::VALID],
            ['//example.com', '//example.com', '//example.com', self::VALID],
            ['http:/example.com', 'http://example.com', 'http://example.com', self::INVALID],
            ['http://example.com', 'http/example.com', 'http://example.com', self::INVALID],
            ['http://example.com', 'http://example.com', 'http/example.com', self::INVALID],
        ];
    }
}
