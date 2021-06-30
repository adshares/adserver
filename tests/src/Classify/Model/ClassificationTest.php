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

namespace Adshares\Tests\Classify\Model;

use Adshares\Classify\Domain\Model\Classification;
use Adshares\Common\Domain\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;

final class ClassificationTest extends TestCase
{
    private const NAMESPACE = 'classify';

    public function testKeywordWhenSiteIdIsNotNull(): void
    {
        $publisherId = 1;
        $siteId = 1;
        $status = true;

        $classification = new Classification(self::NAMESPACE, $publisherId, $status, $siteId);
        $expectedKeyword = sprintf('%s:%s:%s', $publisherId, $siteId, $status);

        $this->assertEquals(self::NAMESPACE, $classification->getNamespace());
        $this->assertEquals($expectedKeyword, $classification->keyword());
    }

    public function testKeywordWhenSiteIdIsNull(): void
    {
        $publisherId = 1;
        $status = false;

        $classification = new Classification(self::NAMESPACE, $publisherId, $status);
        $expectedKeyword = sprintf('%s:%s', $publisherId, (int)$status);

        $this->assertEquals(self::NAMESPACE, $classification->getNamespace());
        $this->assertEquals($expectedKeyword, $classification->keyword());
    }
}
