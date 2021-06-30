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

use Adshares\Supply\Domain\ValueObject\Exception\InvalidUrlException;
use Adshares\Supply\Domain\ValueObject\SourceCampaign;
use DateTime;
use PHPUnit\Framework\TestCase;

final class SourceCampaignTest extends TestCase
{
    public function testWhenHostIsInvalid(): void
    {
        $this->expectException(InvalidUrlException::class);

        new SourceCampaign('', '0001-00000001-0001', '0.1', new DateTime(), new DateTime());
    }

    public function testWhenInputDataAreCorrect(): void
    {
        $createdAt = new DateTime();
        $updatedAt = new DateTime();
        $source = new SourceCampaign('localhost', '0001-00000001-0001', '0.1', $createdAt, $updatedAt);

        $this->assertEquals('localhost', $source->getHost());
        $this->assertEquals('0001-00000001-0001', $source->getAddress());
        $this->assertEquals('0.1', $source->getVersion());
        $this->assertEquals($createdAt, $source->getCreatedAt());
        $this->assertEquals($updatedAt, $source->getUpdatedAt());
    }
}
