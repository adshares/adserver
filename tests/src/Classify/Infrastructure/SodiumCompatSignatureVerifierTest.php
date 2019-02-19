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

namespace Adshares\Tests\Classify\Infrastructure;

use Adshares\Classify\Infrastructure\Service\SodiumCompatSignatureVerifier;
use Adshares\Common\Domain\ValueObject\Uuid;
use PHPUnit\Framework\TestCase;

final class SodiumCompatSignatureVerifierTest extends TestCase
{
    private const PRIVATE_KEY = '0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF0123456789ABCDEF';

    public function testOne(): void
    {
        $verifier = new SodiumCompatSignatureVerifier(self::PRIVATE_KEY);
        $publisherId = (string)Uuid::v4();
        $siteId = (string)Uuid::v4();
        $bannerId = (string)Uuid::v4();
        $status = 1;
        $keyword = sprintf('classify:%s:%s:%s', $publisherId, $siteId, $status);

        $signature = $verifier->create($keyword, $bannerId);

        $this->assertEquals(128, strlen($signature));
    }
}
