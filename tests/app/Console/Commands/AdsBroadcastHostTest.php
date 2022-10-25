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

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Ads\AdsClient;
use Adshares\Ads\Response\TransactionResponse;
use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Adserver\ViewModel\ServerEventType;

class AdsBroadcastHostTest extends ConsoleTestCase
{
    private const COMMAND_SIGNATURE = 'ads:broadcast-host';

    public function testBroadcast(): void
    {
        $adsClient = $this->createMock(AdsClient::class);
        $adsClient->expects(self::once())
            ->method('runTransaction')
            ->willReturn(self::getBroadcastResponse());
        $this->instance(AdsClient::class, $adsClient);

        $this->artisan(self::COMMAND_SIGNATURE)
            ->expectsOutput('Url (https://example.com/info.json) broadcast successfully. TxId: 0001:00000002:0001');
        self::assertServerEventDispatched(ServerEventType::BroadcastSent);
    }

    public function testLock(): void
    {
        $lockerMock = self::createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        $this->artisan(self::COMMAND_SIGNATURE)
            ->expectsOutput('Command ads:broadcast-host already running');
    }

    private static function getBroadcastResponse(): TransactionResponse
    {
        // phpcs:disable Generic.Files.LineLength.TooLong
        $raw = json_decode(
            <<<JSON
{
    "current_block_time": "1665484800",
    "previous_block_time": "1665484288",
    "tx": {
        "data": "030400A00500000100000073494563130068747470733A2F2F6578616D706C652E636F6D2F696E666F2E6A736F6E",
        "signature": "7C680FF2AAB441CE56320BE4F8AAB0313025DB01197A2929EE067067617F5D73538E724A025B3C41114130A5BE39345B6D7BBDDA6D83E81CD7A950CE00F59606",
        "time": "1665485171",
        "account_msid": "1",
        "account_hashin": "06357004654D7E4E5312C37BFBF70D291C76AE19D9804A875D9526AEE8033449",
        "account_hashout": "A7498AFBA8280ADC1A824B27E8950BE994611B98242791F45773BDA7B5D1C2DF",
        "deduct": "0.00000010000",
        "fee": "0.00000010000",
        "id": "0001:00000002:0001"
    }
}
JSON,
            true
        );
        // phpcs:enable Generic.Files.LineLength.TooLong

        return new TransactionResponse($raw);
    }
}
