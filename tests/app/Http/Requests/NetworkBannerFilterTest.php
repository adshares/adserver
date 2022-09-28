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

namespace Adshares\Adserver\Tests\Http\Requests;

use Adshares\Adserver\Http\Request\Classifier\NetworkBannerFilter;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Adserver\Utilities\DatabaseConfigReader;
use Adshares\Common\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

final class NetworkBannerFilterTest extends TestCase
{
    public function testValid(): void
    {
        $request = self::getRequest();

        $filter = new NetworkBannerFilter($request, 1, 2);

        self::assertFalse($filter->isApproved());
        self::assertFalse($filter->isRejected());
        self::assertFalse($filter->isUnclassified());
        self::assertEquals(['300x250'], $filter->getSizes());
        self::assertEquals('image', $filter->getType());
        self::assertFalse($filter->isLocal());
        self::assertEquals('0123456789ABCDEF0123456789ABCDEF', (string)$filter->getNetworkBannerPublicId());
        self::assertEquals('https://example.com', $filter->getLandingUrl());
        self::assertEquals(1, $filter->getUserId());
        self::assertEquals(2, $filter->getSiteId());
    }

    public function testInvalidUuid(): void
    {
        $request = self::getRequest(['banner_id' => '1']);

        self::expectException(InvalidArgumentException::class);
        new NetworkBannerFilter($request, 1, 2);
    }

    public function testInvalidMultipleStatuses(): void
    {
        $request = self::getRequest(['approved' => true, 'rejected' => true, 'unclassified' => true]);

        self::expectException(InvalidArgumentException::class);
        new NetworkBannerFilter($request, 1, 2);
    }

    public function testInvalidType(): void
    {
        $request = self::getRequest(['type' => 'invalid']);

        self::expectException(InvalidArgumentException::class);
        new NetworkBannerFilter($request, 1, 2);
    }

    public function testInvalidSize(): void
    {
        $request = self::getRequest(['sizes' => '["-invalid-invalid-"]']);

        self::expectException(InvalidArgumentException::class);
        new NetworkBannerFilter($request, 1, 2);
    }

    public function testOnlyLocal(): void
    {
        Config::updateAdminSettings(
            [Config::SITE_CLASSIFIER_LOCAL_BANNERS => Config::CLASSIFIER_LOCAL_BANNERS_LOCAL_ONLY]
        );
        DatabaseConfigReader::overwriteAdministrationConfig();
        $filter = new NetworkBannerFilter(self::getRequest(), 1, 2);
        self::assertTrue($filter->isLocal());
    }

    private function getRequest(array $mergeData = []): Request
    {
        $request = self::createMock(Request::class);

        $data = array_merge(
            [
                'approved' => false,
                'rejected' => false,
                'unclassified' => false,
                'sizes' => '["300x250"]',
                'type' => 'image',
                'local' => false,
                'banner_id' => '0123456789ABCDEF0123456789ABCDEF',
                'landing_url' => 'https://example.com',
            ],
            $mergeData
        );

        $request->method('get')->willReturnCallback(
            function (string $field, $default = null) use ($data) {
                if (array_key_exists($field, $data)) {
                    return $data[$field];
                }
                return $default;
            }
        );

        return $request;
    }
}
