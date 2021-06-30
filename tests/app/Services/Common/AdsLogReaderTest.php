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

namespace Adshares\Adserver\Tests\Services\Common;

use Adshares\Ads\AdsClient;
use Adshares\Ads\Command\GetLogCommand;
use Adshares\Ads\Exception\CommandException;
use Adshares\Ads\Response\GetLogResponse;
use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Services\Common\AdsLogReader;
use Adshares\Adserver\Tests\TestCase;
use DateTime;
use DateTimeInterface;

final class AdsLogReaderTest extends TestCase
{
    public function testAdsTxInConsecutiveCalls(): void
    {
        $adsClient = $this->createMock(AdsClient::class);
        $adsClient->method('getLog')->will(
            $this->returnCallback(
                function ($d) {
                    if (null === $d) {
                        $ts = 0;
                    } else {
                        /** @var $d DateTime */
                        $ts = $d->getTimestamp();
                    }
                    switch ($ts) {
                        case 1539606265:
                            $response = new GetLogResponse(json_decode($this->getLog2(), true));
                            break;
                        case 0:
                        default:
                            $response = new GetLogResponse(json_decode($this->getLog1(), true));
                            break;
                    }

                    return $response;
                }
            )
        );

        /** @var AdsClient $adsClient */
        (new AdsLogReader($adsClient))->parseLog();

        $from = Config::where('key', Config::ADS_LOG_START)->first();
        $expectedDate = (new DateTime('@1539606265'))->format(DateTimeInterface::ATOM);
        $this->assertEquals($expectedDate, $from->value);
        $this->assertEquals(12, AdsPayment::all()->count());
        $this->assertEquals(12, AdsPayment::where('status', AdsPayment::STATUS_NEW)->count());

        (new AdsLogReader($adsClient))->parseLog();

        $from = Config::where('key', Config::ADS_LOG_START)->first();
        $this->assertEquals($expectedDate, $from->value);
        $this->assertEquals(12, AdsPayment::all()->count());
        $this->assertEquals(12, AdsPayment::where('status', AdsPayment::STATUS_NEW)->count());
    }

    public function testAdsTxInLogEmpty(): void
    {
        $adsClient = $this->createMock(AdsClient::class);
        $adsClient->method('getLog')->willReturn(new GetLogResponse(json_decode($this->getLogEmpty(), true)));

        /** @var AdsClient $adsClient */
        (new AdsLogReader($adsClient))->parseLog();

        $from = Config::where('key', Config::ADS_LOG_START)->first();
        $this->assertNull($from);
        $this->assertEquals(0, AdsPayment::all()->count());
        $this->assertEquals(0, AdsPayment::where('status', AdsPayment::STATUS_NEW)->count());
    }

    public function testAdsTxInLogException(): void
    {
        $adsClient = $this->createMock(AdsClient::class);
        $exception = new CommandException(new GetLogCommand(new DateTime()), 'Process timed out');
        $adsClient->method('getLog')->willThrowException($exception);

        $this->expectException(CommandException::class);

        /** @var AdsClient $adsClient */
        (new AdsLogReader($adsClient))->parseLog();

        $from = Config::where('key', Config::ADS_LOG_START)->first();
        $this->assertNull($from);
        $this->assertEquals(0, AdsPayment::all()->count());
        $this->assertEquals(0, AdsPayment::where('status', AdsPayment::STATUS_NEW)->count());
    }

    private function getLog2(): string
    {
        return '{
            "current_block_time": "1539606240",
            "previous_block_time": "1539606208",
            "tx": {
                "data": "11010001000000FA86C45B",
                "signature": "51C3574328936FAC497A05B0F45E5AD84D4F20D9D2B3F1AFE933FDEBCF50024EED1D3BC0D'
            . '95BCD2443961B742A06077E7589C78EF94B290974984226FDE8A705",
                "time": "1539606266",
                "account_msid": "0",
                "account_hashin": "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF",
                "account_hashout": "B5DEFCD2F7215A67FA9EDAD9A093597DE6AF6C2A6A3BFB40FE28BA1742BE86CF",
                "deduct": "0.00000000000",
                "fee": "0.00000000000"
            },
            "account": {
                "address": "0001-00000001-8B4E",
                "node": "1",
                "id": "1",
                "msid": "3",
                "time": "1539606267",
                "date": "2018-10-15 14:24:27",
                "status": "0",
                "paired_node": "0",
                "paired_id": "0",
                "local_change": "1539606240",
                "remote_change": "1539605824",
                "balance": "0.00020000000",
                "public_key": "6431A8580B014DA2420FF32842B0BA3CAB3B77F01D1150E5A0D34743F243B778",
                "hash": "8CEEF0B68910FF40333FAECD88B0DEBF4881018C0517D635DA7C34803382B0CE"
            },
            "log": [{
                    "time": "1539606265",
                    "date": "2018-10-15 14:24:25",
                    "type_no": "32772",
                    "confirmed": "no",
                    "type": "send_one",
                    "node": "1",
                    "account": "0",
                    "address": "0001-00000000-9B6F",
                    "node_msid": "15",
                    "node_mpos": "2",
                    "account_msid": "12",
                    "amount": "10752128.71192856647",
                    "sender_fee": "5376.06435596428",
                    "message": "0000000000000000000000000000000000000000000000000000000000000000",
                    "inout": "in",
                    "id": "0001:0000000F:0002"
                }, {
                    "time": "1539606265",
                    "date": "2018-10-15 14:24:27",
                    "type_no": "4",
                    "confirmed": "no",
                    "type": "send_one",
                    "node": "1",
                    "account": "0",
                    "address": "0001-00000000-9B6F",
                    "node_msid": "15",
                    "node_mpos": "3",
                    "account_msid": "2",
                    "amount": "-10746955.23432140578",
                    "sender_fee": "5373.47761716070",
                    "message": "0000000000000000000000000000000000000000000000000000000000000000",
                    "inout": "out",
                    "id": "0001:0000000F:0003"
                }
            ]
        }';
    }

    private function getLog1(): string
    {
        return '{
            "current_block_time": "1539606240",
            "previous_block_time": "1539606208",
            "tx": {
                "data": "11010001000000FA86C45B",
                "signature": "51C3574328936FAC497A05B0F45E5AD84D4F20D9D2B3F1AFE933FDEBCF5002'
            . '4EED1D3BC0D95BCD2443961B742A06077E7589C78EF94B290974984226FDE8A705",
                "time": "1539606266",
                "account_msid": "0",
                "account_hashin": "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF",
                "account_hashout": "B5DEFCD2F7215A67FA9EDAD9A093597DE6AF6C2A6A3BFB40FE28BA1742BE86CF",
                "deduct": "0.00000000000",
                "fee": "0.00000000000"
            },
            "account": {
                "address": "0001-00000001-8B4E",
                "node": "1",
                "id": "1",
                "msid": "3",
                "time": "1539606267",
                "date": "2018-10-15 14:24:27",
                "status": "0",
                "paired_node": "0",
                "paired_id": "0",
                "local_change": "1539606240",
                "remote_change": "1539605824",
                "balance": "0.00020000000",
                "public_key": "6431A8580B014DA2420FF32842B0BA3CAB3B77F01D1150E5A0D34743F243B778",
                "hash": "8CEEF0B68910FF40333FAECD88B0DEBF4881018C0517D635DA7C34803382B0CE"
            },
            "log": [{
                    "time": "1539605856",
                    "date": "2018-10-15 14:17:36",
                    "type_no": "32768",
                    "confirmed": "yes",
                    "type": "node_started",
                    "node_start_msid": "0",
                    "node_start_block": "0",
                    "account": {
                        "balance": "2153233.66666666666",
                        "local_change": "1539605824",
                        "remote_change": "1539605824",
                        "hash_prefix_8": "EDECC56274A500FB",
                        "public_key_prefix_6": "6431A8580B01",
                        "status": "0",
                        "msid": "1",
                        "node": "0",
                        "id": "0",
                        "address": "0000-00000000-313E"
                    },
                    "dividend": "0.00000000000"
                }, {
                    "time": "1539605892",
                    "date": "2018-10-15 14:18:12",
                    "type_no": "32784",
                    "confirmed": "yes",
                    "type": "dividend",
                    "node_msid": "1",
                    "block_id": "5BC48580",
                    "dividend": "-0.00020000000"
                }, {
                    "time": "1539606020",
                    "date": "2018-10-15 14:20:20",
                    "type_no": "32784",
                    "confirmed": "yes",
                    "type": "dividend",
                    "node_msid": "4",
                    "block_id": "5BC48600",
                    "dividend": "-0.00020000000"
                }, {
                    "time": "1539606138",
                    "date": "2018-10-15 14:22:18",
                    "type_no": "32772",
                    "confirmed": "yes",
                    "type": "send_one",
                    "node": "1",
                    "account": "0",
                    "address": "0001-00000000-9B6F",
                    "node_msid": "7",
                    "node_mpos": "1",
                    "account_msid": "1",
                    "amount": "100.00000000000",
                    "sender_fee": "0.05000000000",
                    "message": "0000000000000000000000000000000000000000000000000000000000000000",
                    "inout": "in",
                    "id": "0001:00000007:0001"
                }, {
                    "time": "1539606139",
                    "date": "2018-10-15 14:22:19",
                    "type_no": "32772",
                    "confirmed": "yes",
                    "type": "send_one",
                    "node": "1",
                    "account": "0",
                    "address": "0001-00000000-9B6F",
                    "node_msid": "8",
                    "node_mpos": "1",
                    "account_msid": "2",
                    "amount": "0.00000000001",
                    "sender_fee": "0.00000010000",
                    "message": "0000000000000000000000000000000000000000000000000000000000000000",
                    "inout": "in",
                    "id": "0001:00000008:0001"
                }, {
                    "time": "1539606148",
                    "date": "2018-10-15 14:22:28",
                    "type_no": "32784",
                    "confirmed": "yes",
                    "type": "dividend",
                    "node_msid": "7",
                    "block_id": "5BC48680",
                    "dividend": "-0.00020000000"
                }, {
                    "time": "1539606200",
                    "date": "2018-10-15 14:23:20",
                    "type_no": "32772",
                    "confirmed": "yes",
                    "type": "send_one",
                    "node": "1",
                    "account": "0",
                    "address": "0001-00000000-9B6F",
                    "node_msid": "9",
                    "node_mpos": "1",
                    "account_msid": "3",
                    "amount": "100.00000000000",
                    "sender_fee": "0.05000000000",
                    "message": "0000000000000000000000000000000000000000000000000000000000000000",
                    "inout": "in",
                    "id": "0001:00000009:0001"
                }, {
                    "time": "1539606202",
                    "date": "2018-10-15 14:23:22",
                    "type_no": "32772",
                    "confirmed": "yes",
                    "type": "send_one",
                    "node": "1",
                    "account": "0",
                    "address": "0001-00000000-9B6F",
                    "node_msid": "9",
                    "node_mpos": "2",
                    "account_msid": "4",
                    "amount": "0.00000000001",
                    "sender_fee": "0.00000010000",
                    "message": "0000000000000000000000000000000000000000000000000000000000000000",
                    "inout": "in",
                    "id": "0001:00000009:0002"
                }, {
                    "time": "1539606208",
                    "date": "2018-10-15 14:23:28",
                    "type_no": "32773",
                    "confirmed": "yes",
                    "type": "send_many",
                    "node": "1",
                    "account": "0",
                    "address": "0001-00000000-9B6F",
                    "node_msid": "10",
                    "node_mpos": "1",
                    "account_msid": "5",
                    "amount": "100.00000000000",
                    "sender_balance": "8612534.47377770862",
                    "sender_amount": "200.00000000000",
                    "sender_fee": "0.05000000000",
                    "sender_fee_total": "0.10000000000",
                    "sender_public_key_prefix_5": "A9C0D972D8",
                    "sender_status": "0",
                    "inout": "in",
                    "id": "0001:0000000A:0001"
                }, {
                    "time": "1539606213",
                    "date": "2018-10-15 14:23:33",
                    "type_no": "32772",
                    "confirmed": "yes",
                    "type": "send_one",
                    "node": "1",
                    "account": "0",
                    "address": "0001-00000000-9B6F",
                    "node_msid": "11",
                    "node_mpos": "1",
                    "account_msid": "6",
                    "amount": "0.00001000000",
                    "sender_fee": "0.00000010000",
                    "message": "6D7677A97C7AE35A8E762B4E265CBAEDD3EF5895306586DA56B533648B6137E1",
                    "inout": "in",
                    "id": "0001:0000000B:0001"
                }, {
                    "time": "1539606216",
                    "date": "2018-10-15 14:23:36",
                    "type_no": "32772",
                    "confirmed": "yes",
                    "type": "send_one",
                    "node": "1",
                    "account": "0",
                    "address": "0001-00000000-9B6F",
                    "node_msid": "11",
                    "node_mpos": "2",
                    "account_msid": "7",
                    "amount": "8608229.36641765277",
                    "sender_fee": "4304.11468320882",
                    "message": "0000000000000000000000000000000000000000000000000000000000000000",
                    "inout": "in",
                    "id": "0001:0000000B:0002"
                }, {
                    "time": "1539606218",
                    "date": "2018-10-15 14:23:38",
                    "type_no": "4",
                    "confirmed": "yes",
                    "type": "send_one",
                    "node": "1",
                    "account": "0",
                    "address": "0001-00000000-9B6F",
                    "node_msid": "11",
                    "node_mpos": "3",
                    "account_msid": "1",
                    "amount": "-10756384.83987438226",
                    "sender_fee": "5378.19241993719",
                    "message": "0000000000000000000000000000000000000000000000000000000000000000",
                    "inout": "out",
                    "id": "0001:0000000B:0003"
                }, {
                    "time": "1539606250",
                    "date": "2018-10-15 14:24:10",
                    "type_no": "32772",
                    "confirmed": "no",
                    "type": "send_one",
                    "node": "1",
                    "account": "0",
                    "address": "0001-00000000-9B6F",
                    "node_msid": "12",
                    "node_mpos": "1",
                    "account_msid": "8",
                    "amount": "100.00000000000",
                    "sender_fee": "0.05000000000",
                    "message": "0000000000000000000000000000000000000000000000000000000000000000",
                    "inout": "in",
                    "id": "0001:0000000C:0001"
                }, {
                    "time": "1539606251",
                    "date": "2018-10-15 14:24:11",
                    "type_no": "32772",
                    "confirmed": "no",
                    "type": "send_one",
                    "node": "1",
                    "account": "0",
                    "address": "0001-00000000-9B6F",
                    "node_msid": "13",
                    "node_mpos": "1",
                    "account_msid": "9",
                    "amount": "0.00000000001",
                    "sender_fee": "0.00000010000",
                    "message": "0000000000000000000000000000000000000000000000000000000000000000",
                    "inout": "in",
                    "id": "0001:0000000D:0001"
                }, {
                    "time": "1539606257",
                    "date": "2018-10-15 14:24:17",
                    "type_no": "32773",
                    "confirmed": "no",
                    "type": "send_many",
                    "node": "1",
                    "account": "0",
                    "address": "0001-00000000-9B6F",
                    "node_msid": "14",
                    "node_mpos": "1",
                    "account_msid": "10",
                    "amount": "100.00000000000",
                    "sender_balance": "10757505.77629463075",
                    "sender_amount": "200.00000000000",
                    "sender_fee": "0.05000000000",
                    "sender_fee_total": "0.10000000000",
                    "sender_public_key_prefix_5": "A9C0D972D8",
                    "sender_status": "0",
                    "inout": "in",
                    "id": "0001:0000000E:0001"
                }, {
                    "time": "1539606262",
                    "date": "2018-10-15 14:24:22",
                    "type_no": "32772",
                    "confirmed": "no",
                    "type": "send_one",
                    "node": "1",
                    "account": "0",
                    "address": "0001-00000000-9B6F",
                    "node_msid": "15",
                    "node_mpos": "1",
                    "account_msid": "11",
                    "amount": "0.00001000000",
                    "sender_fee": "0.00000010000",
                    "message": "4E1F5E5B66858C9D90190D118E28C0D61D453B5F9F61949698804451777AF4E3",
                    "inout": "in",
                    "id": "0001:0000000F:0001"
                }, {
                    "time": "1539606265",
                    "date": "2018-10-15 14:24:25",
                    "type_no": "32772",
                    "confirmed": "no",
                    "type": "send_one",
                    "node": "1",
                    "account": "0",
                    "address": "0001-00000000-9B6F",
                    "node_msid": "15",
                    "node_mpos": "2",
                    "account_msid": "12",
                    "amount": "10752128.71192856647",
                    "sender_fee": "5376.06435596428",
                    "message": "0000000000000000000000000000000000000000000000000000000000000000",
                    "inout": "in",
                    "id": "0001:0000000F:0002"
                }, {
                    "time": "1539606265",
                    "date": "2018-10-15 14:24:27",
                    "type_no": "4",
                    "confirmed": "no",
                    "type": "send_one",
                    "node": "1",
                    "account": "0",
                    "address": "0001-00000000-9B6F",
                    "node_msid": "15",
                    "node_mpos": "3",
                    "account_msid": "2",
                    "amount": "-10746955.23432140578",
                    "sender_fee": "5373.47761716070",
                    "message": "0000000000000000000000000000000000000000000000000000000000000000",
                    "inout": "out",
                    "id": "0001:0000000F:0003"
                }
            ]
        }';
    }

    private function getLogEmpty(): string
    {
        return '{
            "current_block_time": "1539606240",
            "previous_block_time": "1539606208",
            "tx": {
                "data": "11010001000000FA86C45B",
                "signature": "51C3574328936FAC497A05B0F45E5AD84D4F20D9D2B3F1AFE933FDEBCF50024EED1D3BC0'
            . 'D95BCD2443961B742A06077E7589C78EF94B290974984226FDE8A705",
                "time": "1539606266",
                "account_msid": "0",
                "account_hashin": "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF",
                "account_hashout": "B5DEFCD2F7215A67FA9EDAD9A093597DE6AF6C2A6A3BFB40FE28BA1742BE86CF",
                "deduct": "0.00000000000",
                "fee": "0.00000000000"
            },
            "account": {
                "address": "0001-00000001-8B4E",
                "node": "1",
                "id": "1",
                "msid": "3",
                "time": "1539606267",
                "date": "2018-10-15 14:24:27",
                "status": "0",
                "paired_node": "0",
                "paired_id": "0",
                "local_change": "1539606240",
                "remote_change": "1539605824",
                "balance": "0.00020000000",
                "public_key": "6431A8580B014DA2420FF32842B0BA3CAB3B77F01D1150E5A0D34743F243B778",
                "hash": "8CEEF0B68910FF40333FAECD88B0DEBF4881018C0517D635DA7C34803382B0CE"
            },
            "log": ""
        }';
    }
}
