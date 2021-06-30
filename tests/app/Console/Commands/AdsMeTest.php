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
use Adshares\Ads\Response\GetAccountResponse;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;

class AdsMeTest extends ConsoleTestCase
{
    public function testAdsMe()
    {
        $this->app->bind(
            AdsClient::class,
            function () {
                $adsClient = $this->createMock(AdsClient::class);
                $adsClient->method('getMe')->willReturn($this->getMe());

                return $adsClient;
            }
        );

        $this->artisan('ads:me')->expectsOutput('10757805.92629473076')->assertExitCode(0);
    }

    private function getMe()
    {
        return new GetAccountResponse(
            json_decode(
                '{
            "current_block_time": "1539606240",
            "previous_block_time": "1539606208",
            "tx": {
                "data": "10010000000000010000000000E986C45B",
                "signature": "749E930A3054A1ACB7926E34FB18ACC712AED8F9431891F376CE01279A'
                . '9D5B927F80FD2F0BBEE3FE083E7721FF9C7DBCAFED9BD536ED2F634102BE22D2091703",
                "time": "1539606249"
            },
            "account": {
                "address": "0001-00000000-9B6F",
                "node": "1",
                "id": "0",
                "msid": "8",
                "time": "1539606216",
                "date": "2018-10-15 14:23:36",
                "status": "0",
                "paired_node": "0",
                "paired_id": "0",
                "local_change": "1539606208",
                "remote_change": "1539606208",
                "balance": "10757805.92629473076",
                "public_key": "A9C0D972D8AAB73805EC4A28291E052E3B5FAFE0ADC9D724917054E5E2690363",
                "hash": "A3F1B886445EA0F1E6F64D22C8D015F15F6C440B504E314C80FDA572EA66E4AC"
            },
            "network_account": {
                "address": "0001-00000000-9B6F",
                "node": "1",
                "id": "0",
                "msid": "8",
                "time": "1539606216",
                "date": "2018-10-15 14:23:36",
                "status": "0",
                "paired_node": "0",
                "paired_id": "0",
                "local_change": "1539606208",
                "remote_change": "1539606208",
                "balance": "10757805.92629473076",
                "public_key": "A9C0D972D8AAB73805EC4A28291E052E3B5FAFE0ADC9D724917054E5E2690363",
                "hash": "A3F1B886445EA0F1E6F64D22C8D015F15F6C440B504E314C80FDA572EA66E4AC",
                "checksum": "true"
            }
        }',
                true
            )
        );
    }
}
