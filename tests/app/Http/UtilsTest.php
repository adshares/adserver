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

namespace Adshares\Adserver\Tests\Http;

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Zone;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Supply\Application\Dto\UserContext;
use Illuminate\Http\Request;

class UtilsTest extends TestCase
{
    public function testUserIdFromTrackingId(): void
    {
        $uidHex = 'e96438dd5a0e42a6881959886a8ebc2f';

        $tid = Utils::base64UrlEncodeWithChecksumFromBinUuidString(hex2bin($uidHex));

        self::assertSame($uidHex, Utils::hexUuidFromBase64UrlWithChecksum($tid));
    }

    public function testTrackingIdFromUserId(): void
    {
        $tid = '6WQ43VoOQqaIGVmIao68L8k9SIhFSg';

        $uidHex = Utils::hexUuidFromBase64UrlWithChecksum($tid);

        self::assertSame($tid, Utils::base64UrlEncodeWithChecksumFromBinUuidString(hex2bin($uidHex)));
    }

    public function testContext(): void
    {
        $userContext = new UserContext(
            [],
            0.4,
            0.9,
            'ok',
            'HV_ockboEDXZDlp_VcGfN6Dx7DxMPw'
        );

        $userId = $userContext->userId();

        $uid = Uuid::fromString(Utils::hexUuidFromBase64UrlWithChecksum($userId))->hex();
        self::assertSame('1d5fe87246e81035d90e5a7f55c19f37', $uid);

        self::assertSame(
            '{"uid":"HV_ockboEDXZDlp_VcGfN6Dx7DxMPw",'
            . '"keywords":[],'
            . '"human_score":0.4,'
            . '"page_rank":0.9,'
            . '"page_rank_info":"ok"}',
            $userContext->toString()
        );
    }

    /**
     * @dataProvider getZoneTypeByBannerTypeProvider
     */
    public function testGetZoneTypeByBannerType(string $bannerType, string $expectedZoneType): void
    {
        self::assertEquals($expectedZoneType, Utils::getZoneTypeByBannerType($bannerType));
    }

    public function getZoneTypeByBannerTypeProvider(): array
    {
        return [
            Banner::TEXT_TYPE_IMAGE => [Banner::TEXT_TYPE_IMAGE, Zone::TYPE_DISPLAY],
            Banner::TEXT_TYPE_HTML => [Banner::TEXT_TYPE_HTML, Zone::TYPE_DISPLAY],
            Banner::TEXT_TYPE_DIRECT_LINK => [Banner::TEXT_TYPE_DIRECT_LINK, Zone::TYPE_POP],
            Banner::TEXT_TYPE_VIDEO => [Banner::TEXT_TYPE_VIDEO, Zone::TYPE_DISPLAY],
            Banner::TEXT_TYPE_MODEL => [Banner::TEXT_TYPE_MODEL, Zone::TYPE_MODEL],
        ];
    }

    public function testAppendFragment(): void
    {
        self::assertEquals(
            'https://example.com/a.html#300x250',
            Utils::appendFragment('https://example.com/a.html', '300x250')
        );
        self::assertEquals(
            'https://example.com/a.html#300x250',
            Utils::appendFragment('https://example.com/a.html#300x250', '300x250')
        );
    }

    public function testExtractFilename(): void
    {
        self::assertEquals('a.html', Utils::extractFilename('https://example.com/a.html'));
        self::assertEquals('a', Utils::extractFilename('https://example.com/a'));
    }

    public function testGetSiteContextOnEmptyContext(): void
    {
        $request = new Request();
        $context = [];
        self::assertEquals([], Utils::getSiteContext($request, $context));
    }

    public function testGetSiteContextOnMissingUrl(): void
    {
        $request = new Request();
        $request->headers->set('Referer', 'https://example.com/site/8');
        $context = [
            'page' => [],
        ];
        self::assertEquals('example.com', Utils::getSiteContext($request, $context)['domain']);
    }

    public function testGetSiteContextOnInvalidUrl(): void
    {
        $request = new Request();
        $request->headers->set('Referer', 'https://example.com/site/8');
        $context = [
            'page' => ['url' => -1],
        ];
        self::assertEquals('example.com', Utils::getSiteContext($request, $context)['domain']);
    }
}
