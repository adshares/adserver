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

namespace Adshares\Adserver\Tests\Http;

use Adshares\Adserver\Http\Utils;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Supply\Application\Dto\UserContext;

use function hex2bin;

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
        $tid = '6WQ43VoOQqaIGVmIao68L2qb7wUbKQ';

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
}
