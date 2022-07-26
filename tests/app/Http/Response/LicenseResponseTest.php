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

namespace Adshares\Adserver\Tests\Http\Response;

use Adshares\Adserver\Http\Request\Classifier\NetworkBannerFilter;
use Adshares\Adserver\Http\Response\LicenseResponse;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\Commission;
use Adshares\Common\Domain\ValueObject\License;
use Adshares\Common\Exception\InvalidArgumentException;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;

final class LicenseResponseTest extends TestCase
{
    public function testToArray(): void
    {
        $response = new LicenseResponse($this->getLicense());

        self::assertEquals([
            'id' => 'COM-aBcD02',
            'type' => 'COM',
            'status' => 1,
            'dateStart' => '2022-07-25T15:52:03+00:00',
            'dateEnd' => '2023-07-25T15:52:03+00:00',
            'owner' => 'AdServer',
            'detailsUrl' => 'http://license-server/license/COM-aBcD02'
        ], $response->toArray());
    }

    private function getLicense(): License
    {
        return new License(
            'COM-aBcD02',
            'COM',
            1,
            new DateTimeImmutable('@1658764323'),
            new DateTimeImmutable('@1690300323'),
            'AdServer',
            new AccountId('0001-00000024-FF89'),
            new Commission(0.0),
            new Commission(0.01),
            new Commission(0.02),
            true
        );
    }
}
