<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Console;

use Adshares\Ads\AdsClient;
use Adshares\Ads\Command\BroadcastCommand;
use Adshares\Ads\Command\GetAccountCommand;
use Adshares\Ads\Exception\CommandException;
use Adshares\Ads\Response\TransactionResponse;
use Adshares\Adserver\Tests\TestCase;
use PHPUnit\Framework\Assert;

class AdsBroadcastHostTest extends TestCase
{
    public function testAdsBroadcastHost()
    {
        $this->app->bind(
            AdsClient::class,
            function () {
                $adsClient = $this->createMock(AdsClient::class);
                $adsClient->method('runTransaction')->willReturnCallback(
                    function ($command) {
                        /** @var $command BroadcastCommand */
                        $message = $command->getAttributes()['message'];
                        Assert::assertEquals('41645365727665722E61647368617265732E6E6574', $message);

                        return new TransactionResponse(json_decode($this->broadcast(), true));
                    }
                );

                return $adsClient;
            }
        );

        $this->artisan('ads:broadcast-host')
            ->expectsOutput('Broadcast message sent successfully. Txid: [0002:00000C5E:0001]')
            ->assertExitCode(0);
    }

    private function broadcast(): string
    {
        return '{
            "current_block_time": "1542278656",
            "previous_block_time": "1542278144",
            "tx": {
                "data": "0302000400000002000000664FED5B190041645365727665722E61647368617265732E6E6574",
                "signature": "415D3EF62CDCF78E6A4FB77A9C9BE3DB484B7575752BBAB05FD'
            .'47B80C00E8AE605CF897309907738D847C883EED9D0FE3D794FBD56D0F9E80A6291FB78DC5B0B",
                "time": "1542279014",
                "account_msid": "2",
                "account_hashin": "2C4D29BD6E868B42CDC5526678B73FE2BD953E543A589828225D6E373CE4E016",
                "account_hashout": "78461CFB5DCAD81849AA2BC0C2ECFEBA1B3ADF7E976D94968E19DADF0F4B7CF4",
                "deduct": "0.00000010000",
                "fee": "0.00000010000",
                "node_msid": "3166",
                "node_mpos": "1",
                "id": "0002:00000C5E:0001"
            },
            "account": {
                "address": "0002-00000004-3539",
                "node": "2",
                "id": "4",
                "msid": "3",
                "time": "1542279014",
                "date": "2018-11-15 11:50:14",
                "status": "0",
                "paired_node": "0",
                "paired_id": "0",
                "local_change": "1542278656",
                "remote_change": "1542197760",
                "balance": "1043.99939980000",
                "public_key": "860BB97F2E355C094CEFB63A7A1245C3D3073E535087FBACEF573C6EC48E17A9",
                "hash": "78461CFB5DCAD81849AA2BC0C2ECFEBA1B3ADF7E976D94968E19DADF0F4B7CF4"
            }
        }';
    }

    public function testAdsBroadcastHostException()
    {
        $this->app->bind(
            AdsClient::class,
            function () {
                $adsClient = $this->createMock(AdsClient::class);
                $command = new GetAccountCommand('0002-00000004-3539');
                $exception = new CommandException($command, 'Process timed out');
                $adsClient->method('runTransaction')->willThrowException($exception);

                return $adsClient;
            }
        );

        $this->artisan('ads:broadcast-host')
            ->expectsOutput('Cannot send broadcast due to error 0')
            ->assertExitCode(0);
    }
}
