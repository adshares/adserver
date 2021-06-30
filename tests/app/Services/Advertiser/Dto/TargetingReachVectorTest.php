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

namespace Adshares\Adserver\Tests\Services\Advertiser\Dto;

use Adshares\Adserver\Services\Advertiser\Dto\TargetingReachVector;
use Adshares\Adserver\Tests\TestCase;

final class TargetingReachVectorTest extends TestCase
{
    public function testGetPercent(): void
    {
        $string = '10000101011001000010001010001110';
        $expectedPercent = 12 / 32;

        $vector = new TargetingReachVector($this->convertStringToBinaryString($string), 0, 0, 0);

        self::assertEquals($expectedPercent, $vector->getOccurrencePercent());
    }

    private function convertStringToBinaryString(string $element): string
    {
        $baseConvert = join(
            array_map(
                function ($item) {
                    $input = base_convert($item, 2, 16);

                    return str_pad($input, strlen($item) / 4, '0', STR_PAD_LEFT);
                },
                str_split($element, 32)
            )
        );

        return pack('H*', $baseConvert);
    }
}
