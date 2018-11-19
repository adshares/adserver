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
use Adshares\Ads\Driver\CliDriver;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;

final class AdsFetchHostsTest extends TestCase
{
    use RefreshDatabase;

    private $count = -1;

    public function testAdsFetchHosts(): void
    {
        $this->app->bind(AdsClient::class, function () {
            return $this->createAdsClient();
        });

        $this->artisan('ads:fetch-hosts')->assertExitCode(0);
    }

    private function createAdsClient()
    {
        $process = $this->createMock(Process::class);
        $process->method('getExitCode')->willReturn(0);
        $process->method('getOutput')->willReturnCallback(function () {
            return $this->getBroadcastRound();
        });

        $driver = $this->getMockBuilder(CliDriver::class)->setConstructorArgs([
            config('app.adshares_address'),
            config('app.adshares_secret'),
            config('app.adshares_node_host'),
            config('app.adshares_node_port'),
        ])->setMethods(['getProcess'])->getMock();
        $driver->method('getProcess')->willReturn($process);

        /* @var CliDriver $driver */
        return new AdsClient($driver);
    }

    private function getBroadcastRound(): string
    {
        $respArray = [
            $this->getBroadcast(),
            $this->getBroadcastEmpty(),
            $this->getBroadcastExceptionNotReady(),
            $this->getBroadcastExceptionWrongSignature(),
            $this->getBroadcastHost(),
            $this->getBroadcastHostInvalid(),
        ];

        return $this->stripNewLine($respArray[$this->count = ++$this->count % count($respArray)]);
    }

    private function stripNewLine(string $text): string
    {
        return str_replace(["\n", "\r"], '', $text);
    }

    private function getBroadcast(): string
    {
        return '{
            "current_block_time": "1542384128",
            "previous_block_time": "1542383616",
            "tx": {
                "data": "1202000400000000E2EE5B34EBEE5B",
                "signature": "737E09096734163309BD1251A8B7628553F9560EDB5EE7E1586FCED6A5C'
            .'6D332C05CB75E8A5BD0E5D7184EACF54D708706F42C5F42417B4C784A026E1701230B",
                "time": "1542384436"
            },
            "block_time_hex": "5BEEE200",
            "block_time": "1542382080",
            "broadcast_count": "1",
            "log_file": "new",
            "broadcast": [
                {
                    "block_time": "1542382080",
                    "block_date": "2018-11-16 16:28:00",
                    "node": "2",
                    "account": "4",
                    "address": "0002-00000004-3539",
                    "account_msid": "30",
                    "time": "1542382322",
                    "date": "2018-11-16 16:32:02",
                    "data": "030200040000001E000000F2E2EE5B1900",
                    "message": "01020304",
                    "signature": "ED6BAE0D1D6E0A012E0A3F1231A0D9B4BBF42F50626A9971DCE31009A3B'
            .'83ABE17B200C787D86792813FCD70B415450E929F35D5C9DC7B8E32665251E2A98C0E",
                    "input_hash": "B0DC84AF498AE72BD99D59CFF0D7AF6C591DFC987A4E5202BF7F3FC3ACBCB923",
                    "public_key": "860BB97F2E355C094CEFB63A7A1245C3D3073E535087FBACEF573C6EC48E17A9",
                    "verify": "passed",
                    "node_msid": "3259",
                    "node_mpos": "1",
                    "id": "0002:00000CBB:0002",
                    "fee": "0.00000010000"
                }
            ]
        }';
    }

    private function getBroadcastEmpty(): string
    {
        return '{
            "current_block_time": "1542383104",
            "previous_block_time": "1542382592",
            "tx": {
                "data": "1202000400000084793742BDE6EE5B",
                "signature": "5D631A43D0595BF265FE05845781F8C42893F9E31208BE927C7222C7F86C'
            .'360C58CB8338D9532CF2D7BD51F8AFB59224D4E56B5ACE64219CB9FC638AED9BFE0B",
                "time": "1542383293"
            },
            "block_time_hex": "42377800",
            "block_time": "1110931456",
            "broadcast_count": "0"
        }';
    }

    private function getBroadcastExceptionNotReady(): string
    {
        return '{
            "current_block_time": "1542383104",
            "previous_block_time": "1542382592",
            "tx": {
                "data": "1202000400000000E6EE5B56E7EE5B",
                "signature": "96EADE470198C2F8BE2BB50C2A9DEB66E68EE70C867E40BC6BA232B58226BD'
            .'E33CF4024F599241DED3D27BFBAE08F0D777AD54A1CB679B2990D708B21621280B",
                "time": "1542383446"
            },
            "error": "Broadcast not ready, try again later"
        }';
    }

    private function getBroadcastExceptionWrongSignature(): string
    {
        return '{
            "current_block_time": "1542383616",
            "previous_block_time": "1542383104",
            "tx": {
                "data": "120200040000008479374251E8EE5B",
                "signature": "FB8907855B12D532DE82548ADC5D833D559D02521B52D032F9EB4642864'
            .'D9DC24FB27F503A931D24CC4353D1034B5A0A2CE1CD9765EDAA3636C1264FD82DCD02",
                "time": "1542383697"
            },
            "error": "Wrong signature"
        }';
    }

    private function getBroadcastHost(): string
    {
        return '{
            "current_block_time": "1542384128",
            "previous_block_time": "1542383616",
            "tx": {
                "data": "1202000400000000E2EE5B34EBEE5B",
                "signature": "737E09096734163309BD1251A8B7628553F9560EDB5EE7E1586FCED6A5C'
            .'6D332C05CB75E8A5BD0E5D7184EACF54D708706F42C5F42417B4C784A026E1701230B",
                "time": "1542384436"
            },
            "block_time_hex": "5BEEE200",
            "block_time": "1542382080",
            "broadcast_count": "1",
            "log_file": "new",
            "broadcast": [
                {
                    "block_time": "1542382080",
                    "block_date": "2018-11-16 16:28:00",
                    "node": "2",
                    "account": "4",
                    "address": "0002-00000004-3539",
                    "account_msid": "30",
                    "time": "1542382322",
                    "date": "2018-11-16 16:32:02",
                    "data": "030200040000001E000000F2E2EE5B1900",
                    "message": "41645365727665722E6C6F63616C686F737425334138313032",
                    "signature": "ED6BAE0D1D6E0A012E0A3F1231A0D9B4BBF42F50626A9971DCE31009A3B'
            .'83ABE17B200C787D86792813FCD70B415450E929F35D5C9DC7B8E32665251E2A98C0E",
                    "input_hash": "B0DC84AF498AE72BD99D59CFF0D7AF6C591DFC987A4E5202BF7F3FC3ACBCB923",
                    "public_key": "860BB97F2E355C094CEFB63A7A1245C3D3073E535087FBACEF573C6EC48E17A9",
                    "verify": "passed",
                    "node_msid": "3259",
                    "node_mpos": "1",
                    "id": "0002:00000CBB:0001",
                    "fee": "0.00000010000"
                }
            ]
        }';
    }

    private function getBroadcastHostInvalid(): string
    {
        return '{
            "current_block_time": "1542384128",
            "previous_block_time": "1542383616",
            "tx": {
                "data": "1202000400000000E2EE5B34EBEE5B",
                "signature": "737E09096734163309BD1251A8B7628553F9560EDB5EE7E1586FCED6A5C'
            .'6D332C05CB75E8A5BD0E5D7184EACF54D708706F42C5F42417B4C784A026E1701230B",
                "time": "1542384436"
            },
            "block_time_hex": "5BEEE200",
            "block_time": "1542382080",
            "broadcast_count": "1",
            "log_file": "new",
            "broadcast": [
                {
                    "block_time": "1542382080",
                    "block_date": "2018-11-16 16:28:00",
                    "node": "2",
                    "account": "4",
                    "address": "0002-00000004-3539",
                    "account_msid": "30",
                    "time": "1542382322",
                    "date": "2018-11-16 16:32:02",
                    "data": "030200040000001E000000F2E2EE5B1900",
                    "message": "41645365727665722E",
                    "signature": "ED6BAE0D1D6E0A012E0A3F1231A0D9B4BBF42F50626A9971DCE31009A3B'
            .'83ABE17B200C787D86792813FCD70B415450E929F35D5C9DC7B8E32665251E2A98C0E",
                    "input_hash": "B0DC84AF498AE72BD99D59CFF0D7AF6C591DFC987A4E5202BF7F3FC3ACBCB923",
                    "public_key": "860BB97F2E355C094CEFB63A7A1245C3D3073E535087FBACEF573C6EC48E17A9",
                    "verify": "passed",
                    "node_msid": "3259",
                    "node_mpos": "1",
                    "id": "0002:00000CBB:0003",
                    "fee": "0.00000010000"
                }
            ]
        }';
    }
}
