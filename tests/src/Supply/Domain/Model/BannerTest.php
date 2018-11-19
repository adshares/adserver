<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Test\Supply\Domain\Model;

use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Supply\Domain\Model\Banner;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\ValueObject\BannerUrl;
use Adshares\Supply\Domain\ValueObject\Budget;
use Adshares\Supply\Domain\ValueObject\Size;
use Adshares\Supply\Domain\ValueObject\SourceHost;
use Adshares\Supply\Domain\ValueObject\UnsupportedBannerSizeException;
use PHPUnit\Framework\TestCase;
use DateTime;

final class BannerTest extends TestCase
{
    const INVALID_TYPE = false;
    const VALID_TYPE = true;

    /**
     * @param string $type
     * @param bool $valid
     *
     * @dataProvider dataProvider
     */
    public function testWhenTypeIsInvalid(string $type, bool $valid)
    {
        if (!$valid) {
            $this->expectException(UnsupportedBannerSizeException::class);
        }

        $campaign = new Campaign(
            Uuid::v4(),
            UUid::fromString('4a27f6a938254573abe47810a0b03748'),
            1,
            'http://example.com',
            new DateTime(),
            new DateTime(),
            [],
            new Budget(10, null, 2),
            new SourceHost('localhost', '0000-00000000-0001', new DateTime(), new DateTime(), '0.1'),
            Campaign::STATUS_PROCESSING,
            [],
            []
        );

        $bannerUrl = new BannerUrl('http://example.com', 'http://example.com', 'http://example.com');
        $banner = new Banner($campaign, Uuid::v4(), $bannerUrl, $type, new Size(728, 90));

        $this->assertEquals($type, $banner->getType());
    }

    public function dataProvider()
    {
        return [
            ['unsupported_type', self::INVALID_TYPE],
            ['html', self::VALID_TYPE],
            ['image', self::VALID_TYPE],
        ];
    }
}
