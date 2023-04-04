<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

namespace Adshares\Tests\Common\Application\Dto;

use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Dto\CommunityFee;
use Adshares\Common\Exception\InvalidArgumentException;

class CommunityFeeTest extends TestCase
{
    public function testFromArray(): void
    {
        $communityFee = CommunityFee::fromArray(['demandFee' => 0.01, 'accountAddress' => '0001-00000024-FF89']);


        self::assertEquals(0.01, $communityFee->getFee());
        self::assertEquals('0001-00000024-FF89', $communityFee->getAccount()->toString());
    }

    /**
     * @dataProvider fromArrayInvalidProvider
     */
    public function testFromArrayInvalid(array $data): void
    {
        self::expectException(InvalidArgumentException::class);

        CommunityFee::fromArray($data);
    }

    public function fromArrayInvalidProvider(): array
    {
        return [
            'invalid account' => [['accountAddress' => '0001-00000004', 'demandFee' => 0.01]],
            'invalid account type' => [['accountAddress' => 24, 'demandFee' => 0.01]],
            'invalid fee type' => [['accountAddress' => '0001-00000024-FF89', 'demandFee' => 2]],
            'no account' => [['demandFee' => 0.01]],
            'no fee' => [['accountAddress' => '0001-00000024-FF89']],
        ];
    }
}
