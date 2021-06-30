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
use Adshares\Ads\Command\GetBlockIdsCommand;
use Adshares\Ads\Exception\CommandException;
use Adshares\Ads\Response\GetBlockIdsResponse;
use Adshares\Ads\Response\GetTransactionResponse;
use Adshares\Adserver\Console\Commands\AdsProcessTx;
use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\NetworkCase;
use Adshares\Adserver\Models\NetworkHost;
use Adshares\Adserver\Models\NetworkImpression;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Common\Domain\ValueObject\NullUrl;
use Adshares\Mock\Client\DummyAdSelectClient;
use Adshares\Mock\Client\DummyDemandClient;
use Adshares\Supply\Application\Service\AdSelect;
use Adshares\Supply\Application\Service\DemandClient;
use Exception;
use PHPUnit\Framework\MockObject\Stub\ConsecutiveCalls;

class AdsProcessTxTest extends ConsoleTestCase
{
    private const TX_ID_CONNECTION = '0001:00000608:0002';

    private const TX_ID_SEND_MANY = '0001:00000085:0001';

    private const TX_ID_SEND_ONE = '0001:00000083:0001';

    public function testAdsProcessValidUserDeposit(): void
    {
        $depositAmount = 100000000000;

        $adsTx = new AdsPayment();
        $adsTx->txid = self::TX_ID_SEND_ONE;
        $adsTx->amount = $depositAmount;
        $adsTx->address = '0001-00000000-9B6F';
        $adsTx->save();

        /** @var User $user */
        $user = factory(User::class)->create();
        $user->uuid = '00000000000000000000000000000123';
        $user->save();

        $this->assertEquals(AdsPayment::STATUS_NEW, AdsPayment::all()->first()->status);

        $this->artisan('ads:process-tx')->assertExitCode(AdsProcessTx::EXIT_CODE_SUCCESS);

        $this->assertEquals(AdsPayment::STATUS_USER_DEPOSIT, AdsPayment::all()->first()->status);
        $this->assertEquals($depositAmount, $user->getBalance());
    }

    public function testAdsProcessDepositWithoutUser(): void
    {
        $depositAmount = 100000000000;

        $adsTx = new AdsPayment();
        $adsTx->txid = self::TX_ID_SEND_ONE;
        $adsTx->amount = $depositAmount;
        $adsTx->address = '0001-00000000-9B6F';
        $adsTx->save();

        $this->assertEquals(AdsPayment::STATUS_NEW, AdsPayment::all()->first()->status);

        $this->artisan('ads:process-tx')->assertExitCode(AdsProcessTx::EXIT_CODE_SUCCESS);

        $this->assertEquals(AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE, AdsPayment::all()->first()->status);
    }

    public function testAdsProcessEventPayment(): void
    {
        $demandClient = new DummyDemandClient();

        $info = $demandClient->fetchInfo(new NullUrl());
        $networkHost = NetworkHost::registerHost('0001-00000000-9B6F', $info);

        $networkImpression = factory(NetworkImpression::class)->create();
        $paymentDetails = $demandClient->fetchPaymentDetails('', '', 333, 0);

        $publisherIds = [];
        $totalAmount = 0;

        foreach ($paymentDetails as $paymentDetail) {
            $publisherId = $paymentDetail['publisher_id'];

            factory(NetworkCase::class)->create(
                [
                    'case_id' => $paymentDetail['case_id'],
                    'network_impression_id' => $networkImpression->id,
                    'publisher_id' => $publisherId,
                ]
            );

            if (!in_array($publisherId, $publisherIds)) {
                $publisherIds[] = $publisherId;
            }

            $totalAmount += (int)$paymentDetail['event_value'];
        }

        foreach ($publisherIds as $publisherId) {
            factory(User::class)->create(['uuid' => $publisherId]);
        }

        $adsTx = new AdsPayment();
        $adsTx->txid = self::TX_ID_SEND_MANY;
        $adsTx->amount = $totalAmount;
        $adsTx->address = $networkHost->address;
        $adsTx->save();

        $this->app->bind(
            DemandClient::class,
            function () {
                return new DummyDemandClient();
            }
        );

        $this->app->bind(
            AdSelect::class,
            function () {
                return new DummyAdSelectClient();
            }
        );

        $this->assertEquals(AdsPayment::STATUS_NEW, AdsPayment::all()->first()->status);

        $this->artisan('ads:process-tx')->assertExitCode(AdsProcessTx::EXIT_CODE_SUCCESS);

        $this->assertEquals(AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE, AdsPayment::all()->first()->status);
    }

    public function testAdsProcessValidSendMany(): void
    {
        $adsTx = new AdsPayment();
        $adsTx->txid = self::TX_ID_SEND_MANY;
        $adsTx->amount = 300000000000;
        $adsTx->address = '0001-00000000-9B6F';
        $adsTx->save();

        $this->assertEquals(AdsPayment::STATUS_NEW, AdsPayment::all()->first()->status);

        $this->artisan('ads:process-tx')->assertExitCode(AdsProcessTx::EXIT_CODE_SUCCESS);

        $this->assertEquals(AdsPayment::STATUS_EVENT_PAYMENT_CANDIDATE, AdsPayment::all()->first()->status);
    }

    public function testAdsProcessConnectionTx(): void
    {
        $adsTx = new AdsPayment();
        $adsTx->txid = self::TX_ID_CONNECTION;
        $adsTx->amount = 300000000000;
        $adsTx->address = '0001-00000000-9B6F';
        $adsTx->save();

        $this->assertEquals(AdsPayment::STATUS_NEW, AdsPayment::all()->first()->status);

        $this->artisan('ads:process-tx')->assertExitCode(AdsProcessTx::EXIT_CODE_SUCCESS);

        $this->assertEquals(AdsPayment::STATUS_INVALID, AdsPayment::all()->first()->status);
    }

    public function testAdsProcessGetBlockIdsError(): void
    {
        $this->app->bind(
            AdsClient::class,
            function () {
                $adsClient = $this->createMock(AdsClient::class);
                // getBlockIds
                $command = new GetBlockIdsCommand('0', '5B400000');
                $exception = new CommandException($command, 'Process timed out');
                $adsClient->method('getBlockIds')->willThrowException($exception);

                return $adsClient;
            }
        );

        $adsTx = new AdsPayment();
        $adsTx->txid = self::TX_ID_SEND_ONE;
        $adsTx->amount = 100000000000;
        $adsTx->address = '0001-00000000-9B6F';
        $adsTx->save();

        $this->assertEquals(AdsPayment::STATUS_NEW, AdsPayment::all()->first()->status);

        $this->artisan('ads:process-tx')->assertExitCode(AdsProcessTx::EXIT_CODE_CANNOT_GET_BLOCK_IDS);

        $this->assertEquals(AdsPayment::STATUS_NEW, AdsPayment::all()->first()->status);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockAdsClient();
    }

    private function mockAdsClient(): void
    {
        $this->app->bind(
            AdsClient::class,
            function () {
                $adsClient = $this->createMock(AdsClient::class);
                // getBlockIds
                $getBlockIdsStub = new ConsecutiveCalls(
                    [
                        $this->getBlockIds1(),
                        $this->getBlockIds2(),
                    ]
                );
                $adsClient->method('getBlockIds')->will($getBlockIdsStub);

                // getTransaction
                $txIds = [
                    self::TX_ID_CONNECTION,
                    self::TX_ID_SEND_MANY,
                    self::TX_ID_SEND_ONE,
                ];
                $map = [];
                foreach ($txIds as $txId) {
                    array_push($map, [$txId, $this->getTx($txId)]);
                }
                $adsClient->method('getTransaction')->will($this->returnValueMap($map));

                return $adsClient;
            }
        );
    }

    private function getBlockIds1(): GetBlockIdsResponse
    {
        return new GetBlockIdsResponse(
            json_decode(
                '{
            "current_block_time": "1539704320",
            "previous_block_time": "1539703808",
            "tx": {
                "data": "13010005000000AB07C65B0000C65B00000000",
                "signature": "165A66C3F7B81FC8E0A3684C57EF9F3DDDD64C4A52E64FF953F4A7C9CF7348882'
                . 'E4FB214EF1393BA94BDCC071A15B3C2C5400A9430CFA2CC8AEFA3CAE645E708",
                "time": "1539704747"
            },
            "updated_blocks": "3",
            "blocks": [
                "5BC60000",
                "5BC60200",
                "5BC60400"
            ]
        }',
                true
            )
        );
    }

    private function getBlockIds2(): GetBlockIdsResponse
    {
        return new GetBlockIdsResponse(
            json_decode(
                '{
            "current_block_time": "1539704320",
            "previous_block_time": "1539703808",
            "tx": {
                "data": "13010005000000AD07C65B0006C65B00000000",
                "signature": "D709A9B39D8002A3DCE42563EF2D5234B3676AA4997D736621F8A99F53A7'
                . 'A35A7D971B33E76A9E7232496ED68765CC39E8B9362F220CA4849CCFE4A08D24F80B",
                "time": "1539704749"
            },
            "updated_blocks": "0"
        }',
                true
            )
        );
    }

    /**
     * @param string $txid
     *
     * @return GetTransactionResponse
     * @throws Exception
     */
    private function getTx(string $txid): GetTransactionResponse
    {
        switch ($txid) {
            case self::TX_ID_CONNECTION:
                $response = $this->getTxConnection();
                break;
            case self::TX_ID_SEND_MANY:
                $response = $this->getTxSendMany();
                break;
            case self::TX_ID_SEND_ONE:
                $response = $this->getTxSendOne();
                break;

            default:
                throw new Exception();
        }

        return new GetTransactionResponse(json_decode($response, true));
    }

    private function getTxConnection(): string
    {
        return '{
            "current_block_time": "1539860480",
            "previous_block_time": "1539859968",
            "tx": {
                "data": "140100020000000568C85B0100080600000200",
                "signature": "08463696728F197EE3CC93DDFC4EA8F91C8BE1EBC1E086A0C13336F3FAB19A7410E61'
            . '1E8752FF79A4D8EC4F560F0EA7DD80579621C7411297C22D20155533D0E",
                "time": "1539860485",
                "account_msid": "0",
                "account_hashin": "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF",
                "account_hashout": "75B934A19CC7B55633C3AF3982599BF004524633442F29794CB358386EF6973A",
                "deduct": "0.00000000000",
                "fee": "0.00000000000"
            },
            "network_tx": {
                "id": "0001:00000608:0002",
                "block_time": "1539857408",
                "block_id": "5BC85C00",
                "node": "1",
                "node_msid": "1544",
                "node_mpos": "2",
                "size": "23",
                "hashpath_size": "3",
                "data": "016E1991EF5011312E302E322B322B6766393961613633",
                "hashpath": [
                    "21AA279611D8C7E48B9CEBB760BBAC6D0F1F58FC0BFCB5A8BFF0B7D3C7C74866",
                    "006BE5904D95856A24B0FDAC9EAFF002A10F00C9810EA7016E4617D4300EB3BE",
                    "7381F2CC6118B76293214F1ED595BEF5F1EFC26E693E83DC2C98E24FBBD90885"
                ]
            },
            "txn": {
                "type": "connection",
                "port": "6510",
                "ip_address": "145.239.80.17",
                "version": "1.0.2+2+gf99aa63"
            }
        }';
    }

    private function getTxSendMany(): string
    {
        return '{
            "current_block_time": "1539179488",
            "previous_block_time": "1539179456",
            "tx": {
                "data": "14010000000000F403BE5B0100850000000100",
                "signature": "F501E9507021B49423DE3CBF4BBA7829145D2595873F75070B8A5F1A1D0E71E7BC'
            . '999CA737DA2E5A4B2DFC296D2F441078D47081D10A2C9249BDFE278DB1120F",
                "time": "1539179508",
                "account_msid": "0",
                "account_hashin": "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF",
                "account_hashout": "A88AB7CA15CFB52E3EB6C4E4DC0BA1FC09A7FBC4920553CF605D37E6185BEF91",
                "deduct": "0.00000000000",
                "fee": "0.00000000000"
            },
            "network_tx": {
                "id": "0001:00000085:0001",
                "block_time": "1539178432",
                "block_id": "5BBDFFC0",
                "node": "1",
                "node_msid": "133",
                "node_mpos": "1",
                "size": "95",
                "hashpath_size": "7",
                "data": "0501000000000003000000CDFFBD5B010001000000000000B864D94500000084966'
            . '1171A830B4412AE89A8B0C3A29E05D1AE2D521232529F2CCFD1626B8681D385884C3193'
            . 'D11D3993CD3F9AD90E210D362FEDBE5DCDC092DE8DDBAC4FDA07",
                "hashpath": [
                    "9638C9D17A7BDB5309C46D6B78365853230459AE606BA3181C94456022523F4C",
                    "1737AEDFD0F8EEB8E39BC3DF08C4FEE79197D0CAB7CAB682FE4F26E272DA380F",
                    "D20418CD071D63997A59391AB9BF1DE35C624C1B6000C21338305BEB378594E9",
                    "7670510D4E7D8AB620C07D2569115C76DD626DC073ECC203663FF0DB94B954AF",
                    "8EE49D07979D7891399F2E3B405E4E36CCCE2AE7E1EB88916A03C8EFB8E8F680",
                    "AF4FD5922526C6C8FA2506C45315B25E4A961099E7688A7C044630E44D056956",
                    "954AEDF00ABF157D1E01E678D84F8D903A392A9844C5C25D6A05726C9BCBEB5A"
                ]
            },
            "txn": {
                "type": "send_many",
                "node": "1",
                "user": "0",
                "sender_address": "0001-00000000-9B6F",
                "msg_id": "3",
                "time": "1539178445",
                "wire_count": "1",
                "sender_fee": "0.00150000000",
                "wires": [
                    {
                        "target_node": "1",
                        "target_user": "0",
                        "target_address": "0001-00000005-CBCA",
                        "amount": "3.00000000000"
                    }
                ],
                "signature": "849661171A830B4412AE89A8B0C3A29E05D1AE2D521232529F2CCFD1'
            . '626B8681D385884C3193D11D3993CD3F9AD90E210D362FEDBE5DCDC092DE8DDBAC4FDA07"
            }
        }';
    }

    private function getTxSendOne(): string
    {
        return '{
            "current_block_time": "1539179872",
            "previous_block_time": "1539179840",
            "tx": {
                "data": "140100000000007A05BE5B0100830000000100",
                "signature": "4A6BBAEB8BCEF9702FF5AADEFE2CD3CF74F2F7D5F555F7F2D7E4E96F4CF4511E7B'
            . '894289452AC50AA63964638D4E2EB428ED910309DDC477519CFE428B067F06",
                "time": "1539179898",
                "account_msid": "0",
                "account_hashin": "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF",
                "account_hashout": "1A2CDFA26C7FDD12BD98D1009F34A5B88CDCB2F7DC7E6282BEEDD13B8A0B4684",
                "deduct": "0.00000000000",
                "fee": "0.00000000000"
            },
            "network_tx": {
                "id": "0001:00000083:0001",
                "block_time": "1539178368",
                "block_id": "5BBDFF80",
                "node": "1",
                "node_msid": "131",
                "node_mpos": "1",
                "size": "125",
                "hashpath_size": "7",
                "data": "04010000000000010000008EFFBD5B01000000000000E876481700000000000000000000'
            . '0000000000000000000000000000000000000000000000000086A3F063531BAC0F779F6172FAEDF09A6'
            . '606D64FBEC69E4B6ED779D171FEA7C94E1D430F1782E4621F10D758E1A4D6039162B8303AD359A38A2590D05E0AF909",
                "hashpath": [
                    "4F6441EDA96E38F5994C09F497D1BB15C3CF860163A4BA3703190BF1CF602E42",
                    "7A6068E0B7A098FE0994C9427D8A39A0B7462951EAA80DA0310F8A0B0A2C7E61",
                    "D20418CD011D63997A59391AB9BF1DE35C624C1B6000C21338305BEB378594E9",
                    "1904710E9FD4BA678B28BA5ACBFEB17C43020EC27A5A842771F76738A132CEB6",
                    "F1221A7CAD4E5873EA13449D072DF6B99E7EEC005CCE76A44A12D548FF0583B3",
                    "B6E8891D131FCD325D93C4D587A0DA6F0E8C8F0602638235A750FD83D6D8101C",
                    "DB00586D8E108F14BD53328B63DFE1E8B94E7A9728FDE6D20545966C57934AF8"
                ]
            },
            "txn": {
                "type": "send_one",
                "node": "1",
                "user": "0",
                "msg_id": "1",
                "time": "1539178382",
                "target_node": "1",
                "target_user": "5",
                "sender_fee": "0.00050000000",
                "sender_address": "0001-00000000-9B6F",
                "target_address": "0001-00000005-CBCA",
                "amount": "1.00000000000",
                "message": "0000000000000000000000000000000000000000000000000000000000000123",
                "signature": "86A3F063531BAC0F779F6172FAEDF09A6606D64FBEC69E4B6ED779D17'
            . '1FEA7C94E1D430F1782E4621F10D758E1A4D6039162B8303AD359A38A2590D05E0AF909"
            }
        }';
    }
}
