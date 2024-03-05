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
use Adshares\Ads\Response\RawResponse;
use Adshares\Ads\Response\TransactionResponse;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;

class SendJoiningFeeTest extends ConsoleTestCase
{
    public function testHandle(): void
    {
        $mockAdsClient = $this->createMock(AdsClient::class);
        $mockAdsClient->method('runTransaction')->willReturn($this->sendOne());
        $this->app->bind(AdsClient::class, fn() => $mockAdsClient);

        $this->artisan(
            'ops:supply:joining-fee',
            [
                'address' => '0001-00000001-8B4E',
                'amount' => '10000',
            ],
        )->assertExitCode(0);
    }

    private function sendOne(): TransactionResponse
    {
        // phpcs:disable Generic.Files.LineLength.TooLong
        return new TransactionResponse(
            (new RawResponse(
                json_decode(
                    <<<JSON
{
    "current_block_time": "1532415264",
    "previous_block_time": "1532415232",
    "tx": {
        "data": "040100000000000300000027CD565B01000100000040420F000000000046066ADCA3C787BF6874CE3361EECF7A9969D98F12719DF53440172B5A7D345A",
        "signature": "DABDDABFC25B0C76E33C0E6285F09695EE0193D10DBBC3F2CA39E8183603D7BDC5F62C14FF60A2EFCC23784F7FA380C6F38A2AD6B7DFB95FA2DCA9BA76D04503",
        "time": "1532415271",
        "account_msid": "3",
        "account_hashin": "8592795CE4EE7AAEEC7BA0EBCB4E5B83DF0151B009363FECB99EB39B62549343",
        "account_hashout": "04D526CB20CCE3003B8A2103C5401ABBCAA3F42D03C2392629B6CF923F66323B",
        "deduct": "0.00001010000",
        "fee": "0.00000010000",
        "node_msid": "13",
        "node_mpos": "2",
        "id": "0001:0000000D:0002"
    },
    "account": {
        "address": "0001-00000000-9B6F",
        "node": "1",
        "id": "0",
        "msid": "4",
        "time": "1532415271",
        "date": "2018-07-24 08:54:31",
        "status": "0",
        "paired_node": "1",
        "paired_id": "0",
        "local_change": "1532415264",
        "remote_change": "1532415232",
        "balance": "19999699.84935875759",
        "public_key": "A9C0D972D8AAB73805EC4A28291E052E3B5FAFE0ADC9D724917054E5E2690363",
        "hash": "04D526CB20CCE3003B8A2103C5401ABBCAA3F42D03C2392629B6CF923F66323B"
    }
}
JSON
                    ,
                    true,
                )
            ))->getRawData()
        );
        // phpcs:enable Generic.Files.LineLength.TooLong
    }
}
