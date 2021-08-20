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

namespace Adshares\Adserver\Tests\Models;

use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\Invoice;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Support\Carbon;

class InvoiceTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        config(['app.adserver_id' => 'abc123123123']);
        Config::updateAdminSettings(
            [
                Config::INVOICE_CURRENCIES => 'EUR',
                Config::INVOICE_COMPANY_NAME => 'Operator sp. z o.o.',
                Config::INVOICE_COMPANY_ADDRESS => 'Mock address 11.23/45',
                Config::INVOICE_COMPANY_POSTAL_CODE => '99/456',
                Config::INVOICE_COMPANY_CITY => 'MockCity',
                Config::INVOICE_COMPANY_COUNTRY => 'DE',
                Config::INVOICE_COMPANY_VAT_ID => 'DE999888777',
                Config::INVOICE_COMPANY_BANK_ACCOUNTS => '{"EUR":{"name":"BANK A (ABC)","number":"11 1111 2222 3333"}}',
                Config::INVOICE_NUMBER_FORMAT => 'PROF AAAA/NN/MM/YYYY',
            ]
        );
    }

    public function testGetNextSequence(): void
    {
        $date = new Carbon('2021-08-01');

        $this->assertEquals(1, Invoice::getNextSequence(Invoice::TYPE_PROFORMA, $date->copy()->subMonth()));
        $this->assertEquals(1, Invoice::getNextSequence(Invoice::TYPE_PROFORMA, $date));
        $this->assertEquals(1, Invoice::getNextSequence(Invoice::TYPE_PROFORMA, $date->copy()->addMonth()));
        $this->assertEquals(1, Invoice::getNextSequence(Invoice::TYPE_PROFORMA, $date->copy()->addYear()));

        factory(Invoice::class)->create(['issue_date' => $date]);
        factory(Invoice::class)->create(['issue_date' => $date]);
        factory(Invoice::class)->create(['issue_date' => $date])->delete();

        $this->assertEquals(1, Invoice::getNextSequence(Invoice::TYPE_PROFORMA, $date->copy()->subMonth()));
        $this->assertEquals(4, Invoice::getNextSequence(Invoice::TYPE_PROFORMA, $date));
        $this->assertEquals(1, Invoice::getNextSequence(Invoice::TYPE_PROFORMA, $date->copy()->addMonth()));
        $this->assertEquals(1, Invoice::getNextSequence(Invoice::TYPE_PROFORMA, $date->copy()->addYear()));

        factory(Invoice::class)->create(['issue_date' => $date->copy()->addMonth()]);

        $this->assertEquals(1, Invoice::getNextSequence(Invoice::TYPE_PROFORMA, $date->copy()->subMonth()));
        $this->assertEquals(4, Invoice::getNextSequence(Invoice::TYPE_PROFORMA, $date));
        $this->assertEquals(2, Invoice::getNextSequence(Invoice::TYPE_PROFORMA, $date->copy()->addMonth()));
        $this->assertEquals(1, Invoice::getNextSequence(Invoice::TYPE_PROFORMA, $date->copy()->addYear()));
    }

    public function testCreateProforma(): void
    {
        Carbon::setTestNow('2021-08-04');
        $user = factory(User::class)->create();
        $proforma = Invoice::createProforma(self::getInvoiceData(['user_id' => $user->id]));

        $this->assertNotEmpty($proforma->uuid);
        $this->assertEquals($user->id, $proforma->user_id);
        $this->assertEquals($user->id, $proforma->user->id);
        $this->assertEquals(Invoice::TYPE_PROFORMA, $proforma->type);
        $this->assertEquals('PROF abc1/01/08/2021', $proforma->number);
        $this->assertEquals('2021-08-04', $proforma->issue_date->format('Y-m-d'));
        $this->assertEquals('2021-08-11', $proforma->due_date->format('Y-m-d'));
        $this->assertEquals('Operator sp. z o.o.', $proforma->seller_name);
        $this->assertEquals('Mock address 11.23/45', $proforma->seller_address);
        $this->assertEquals('99/456', $proforma->seller_postal_code);
        $this->assertEquals('MockCity', $proforma->seller_city);
        $this->assertEquals('DE', $proforma->seller_country);
        $this->assertEquals('DE999888777', $proforma->seller_vat_id);
        $this->assertEquals('Foo co.', $proforma->buyer_name);
        $this->assertEquals('Dummy address', $proforma->buyer_address);
        $this->assertEquals('00-111', $proforma->buyer_postal_code);
        $this->assertEquals('Dummy city', $proforma->buyer_city);
        $this->assertEquals('PL', $proforma->buyer_country);
        $this->assertEquals('PL123123123', $proforma->buyer_vat_id);
        $this->assertEquals(1000, $proforma->net_amount);
        $this->assertEquals(1230, $proforma->gross_amount);
        $this->assertEquals(230, $proforma->vat_amount);
        $this->assertEquals('23%', $proforma->vat_rate);
        $this->assertEquals('Test comment', $proforma->comments);
        $this->assertNotEmpty($proforma->html_output);
    }

    public function testCreateProformaVatRate(): void
    {
        $user = factory(User::class)->create();
        $proforma = Invoice::createProforma(
            self::getInvoiceData(
                [
                    'user_id' => $user->id,
                    'buyer_country' => 'ES',
                ]
            )
        );

        $this->assertEquals(1000, $proforma->net_amount);
        $this->assertEquals(1230, $proforma->gross_amount);
        $this->assertEquals(230, $proforma->vat_amount);
        $this->assertEquals('23%', $proforma->vat_rate);

        $proforma = Invoice::createProforma(
            self::getInvoiceData(
                [
                    'user_id' => $user->id,
                    'buyer_country' => 'ES',
                    'eu_vat' => true,
                ]
            )
        );

        $this->assertEquals(1000, $proforma->net_amount);
        $this->assertEquals(1000, $proforma->gross_amount);
        $this->assertEquals(0, $proforma->vat_amount);
        $this->assertEquals('np. EU', $proforma->vat_rate);

        $proforma = Invoice::createProforma(
            self::getInvoiceData(
                [
                    'user_id' => $user->id,
                    'buyer_country' => 'SB',
                ]
            )
        );

        $this->assertEquals(1000, $proforma->net_amount);
        $this->assertEquals(1000, $proforma->gross_amount);
        $this->assertEquals(0, $proforma->vat_amount);
        $this->assertEquals('np.', $proforma->vat_rate);
    }

    public function testCreateProformaTemplate(): void
    {
        Carbon::setTestNow('2021-08-04');
        $user = factory(User::class)->create();
        $proforma = Invoice::createProforma(self::getInvoiceData(['user_id' => $user->id]));

        $this->assertStringContainsString('PROF abc1/01/08/2021', $proforma->html_output);
        $this->assertStringContainsString('04-08-2021', $proforma->html_output);
        $this->assertStringContainsString('11-08-2021', $proforma->html_output);
        $this->assertStringContainsString('Operator sp. z o.o.', $proforma->html_output);
        $this->assertStringContainsString('Mock address 11.23/45', $proforma->html_output);
        $this->assertStringContainsString('99/456', $proforma->html_output);
        $this->assertStringContainsString('MockCity', $proforma->html_output);
        $this->assertStringContainsString('Germany', $proforma->html_output);
        $this->assertStringContainsString('DE999888777', $proforma->html_output);
        $this->assertStringContainsString('Foo co.', $proforma->html_output);
        $this->assertStringContainsString('Dummy address', $proforma->html_output);
        $this->assertStringContainsString('00-111', $proforma->html_output);
        $this->assertStringContainsString('Dummy city', $proforma->html_output);
        $this->assertStringContainsString('Poland', $proforma->html_output);
        $this->assertStringContainsString('PL123123123', $proforma->html_output);
        $this->assertStringContainsString('1 000,00', $proforma->html_output);
        $this->assertStringContainsString('1 230,00', $proforma->html_output);
        $this->assertStringContainsString('230,00', $proforma->html_output);
        $this->assertStringContainsString('23%', $proforma->html_output);
        $this->assertStringContainsString('Test comment', $proforma->html_output);
        $this->assertStringContainsString('BANK A (ABC)', $proforma->html_output);
        $this->assertStringContainsString('11 1111 2222 3333', $proforma->html_output);
        $this->assertStringContainsString('EUR', $proforma->html_output);
    }

    private static function getInvoiceData(array $data = []): array
    {
        return array_merge(
            [
                'buyer_name' => 'Foo co.',
                'buyer_address' => 'Dummy address',
                'buyer_postal_code' => '00-111',
                'buyer_city' => 'Dummy city',
                'buyer_country' => 'PL',
                'buyer_vat_id' => 'PL123123123',
                'currency' => 'EUR',
                'net_amount' => 1000,
                'comments' => 'Test comment',
            ],
            $data
        );
    }
}
