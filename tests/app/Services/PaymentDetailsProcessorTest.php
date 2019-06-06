<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

declare(strict_types = 1);

namespace Adshares\Adserver\Tests\Utilities;

use Adshares\Ads\AdsClient;
use Adshares\Ads\Response\TransactionResponse;
use Adshares\Adserver\Models\AdsPayment;
use Adshares\Adserver\Models\NetworkEventLog;
use Adshares\Adserver\Models\NetworkPayment;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Services\PaymentDetailsProcessor;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use Adshares\Common\Infrastructure\Service\LicenseReader;
use DateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class PaymentDetailsProcessorTest extends TestCase
{
    use RefreshDatabase;

    private const LICENSE_FEE = 0.01;

    private const OPERATOR_FEE = 0.01;

    public function testProcessingEmptyDetails(): void
    {
        $paymentDetailsProcessor = $this->getPaymentDetailsProcessor();

        $adsPayment = $this->createAdsPayment(10000);

        $paymentDetailsProcessor->processPaymentDetails($adsPayment, []);

        $this->assertCount(0, NetworkPayment::all());
    }

    public function testProcessingDetails(): void
    {
        $totalPayment = 10000;
        $paidEventsCount = 2;

        $paymentDetailsProcessor = $this->getPaymentDetailsProcessor();

        $user = factory(User::class)->create();
        $userUuid = $user->uuid;

        $networkEvents = factory(NetworkEventLog::class)->times($paidEventsCount)->create(
            ['event_value' => null, 'publisher_id' => $userUuid]
        );

        $adsPayment = $this->createAdsPayment($totalPayment);

        $paymentDetails = [];
        foreach ($networkEvents as $networkEvent) {
            $paymentDetails[] = [
                'case_id' => $networkEvent->case_id,
                'event_id' => $networkEvent->event_id,
                'event_type' => $networkEvent->event_type,
                'banner_id' => $networkEvent->banner_id,
                'zone_id' => $networkEvent->zone_id,
                'publisher_id' => $userUuid,
                'event_value' => $totalPayment / $paidEventsCount,
            ];
        }

        $paymentDetailsProcessor->processPaymentDetails($adsPayment, $paymentDetails);

        $expectedLicenseAmount = 0;
        $expectedOperatorAmount = 0;
        foreach ($paymentDetails as $paymentDetail) {
            $eventValue = $paymentDetail['event_value'];
            $eventLicenseAmount = (int)floor(self::LICENSE_FEE * $eventValue);
            $expectedLicenseAmount += $eventLicenseAmount;
            $expectedOperatorAmount += (int)floor(self::OPERATOR_FEE * ($eventValue - $eventLicenseAmount));
        }
        $expectedAdIncome = $totalPayment - $expectedLicenseAmount - $expectedOperatorAmount;

        $this->assertCount(1, NetworkPayment::all());
        $licensePayment = NetworkPayment::first();
        $this->assertEquals($expectedLicenseAmount, $licensePayment->amount);

        $this->assertCount(1, UserLedgerEntry::all());
        $userLedgerEntry = UserLedgerEntry::first();
        $this->assertEquals($expectedAdIncome, $userLedgerEntry->amount);
    }

    private function getAdsClient(): AdsClient
    {
        $transactionResponse = new TransactionResponse($this->getTransactionResponseData());

        $adsClient = $this->createMock(AdsClient::class);
        $adsClient->method('runTransaction')->willReturn($transactionResponse);

        /** @var AdsClient $adsClient */
        return $adsClient;
    }

    private function getTransactionResponseData(): array
    {
        return json_decode(
            '{
    "current_block_time": "1559801856",
    "previous_block_time": "1559801344",
    "tx": {
        "data": "040200080000003727000023B0F85C02000800000000E8764817000000000'
            .'0000000000000000000000000000000000000000000000000000000000000",
        "signature": "79C325C5924A8D15ECADD8A53F6E021C08D36F47E068AA9826F1882AC54DCA97'
            .'B5EF9DF50D7985483EE3C78CACF7DC6E6E0F2CCB23627CB5B87C088CAFC3F607",
        "time": "1559801891",
        "account_msid": "10039",
        "account_hashin": "9EF7CD266CBA177E85EB5AAB95C29F92114607E4236DCC3B7E43A6D8F46ABE18",
        "account_hashout": "E10542FA6080FDFE14E8987ED91302CD27BC4B209E2648EDAAFC80FA80FAFCDB",
        "deduct": "1.00050000000",
        "fee": "0.00050000000",
        "node_msid": "12587",
        "node_mpos": "1",
        "id": "0002:0000312B:0001"
    },
    "account": {
        "address": "0002-00000008-F4B5",
        "node": "2",
        "id": "8",
        "msid": "10040",
        "time": "1559801891",
        "date": "2019-06-06 08:18:11",
        "status": "0",
        "paired_node": "0",
        "paired_id": "0",
        "local_change": "1559801856",
        "remote_change": "1559038976",
        "balance": "8569.63805524856",
        "public_key": "9EC3D9A90134000DFAA6317D569FF3C5E7B4A175E31E04B3F712A760F5241715",
        "hash": "E10542FA6080FDFE14E8987ED91302CD27BC4B209E2648EDAAFC80FA80FAFCDB"
    }
}',
            true
        );
    }

    private function getExchangeRateReader(): ExchangeRateReader
    {
        $value = 1;

        $exchangeRateReader = $this->createMock(ExchangeRateReader::class);
        $exchangeRateReader->method('fetchExchangeRate')->willReturn(new ExchangeRate(new DateTime(), $value, 'USD'));

        /** @var ExchangeRateReader $exchangeRateReader */
        return $exchangeRateReader;
    }

    private function getLicenseReader(): LicenseReader
    {
        $licenseReader = $this->createMock(LicenseReader::class);
        $licenseReader->method('getAddress')->willReturn(new AccountId('0001-00000000-9B6F'));
        $licenseReader->method('getFee')->willReturn(self::LICENSE_FEE);

        /** @var LicenseReader $licenseReader */
        return $licenseReader;
    }

    private function getPaymentDetailsProcessor(): PaymentDetailsProcessor
    {
        return new PaymentDetailsProcessor(
            $this->getAdsClient(),
            $this->getExchangeRateReader(),
            $this->getLicenseReader()
        );
    }

    private function createAdsPayment(int $amount): AdsPayment
    {
        $adsPayment = new AdsPayment();
        $adsPayment->txid = '0002:000017C3:0001';
        $adsPayment->amount = $amount;
        $adsPayment->address = '0002-00000007-055A';
        $adsPayment->save();

        return $adsPayment;
    }
}
