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

namespace Adshares\Adserver\Tests\Http\Controllers\Manager;

use Adshares\Adserver\Mail\InvoiceCreated;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\Invoice;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

class InvoicesControllerTest extends TestCase
{
    private const URI = '/api/invoices';

    public function setUp(): void
    {
        parent::setUp();
        Config::updateAdminSettings(
            [
                Config::INVOICE_ENABLED => '1',
                Config::INVOICE_CURRENCIES => 'PLN,USD',
                Config::INVOICE_COMPANY_NAME => 'Operator sp. z o.o.',
                Config::INVOICE_COMPANY_ADDRESS => 'Mock address 11.23/45',
                Config::INVOICE_COMPANY_COUNTRY => 'DE',
                Config::INVOICE_COMPANY_VAT_ID => 'DE999888777',
                Config::INVOICE_COMPANY_BANK_ACCOUNTS => '{"USD":{"name":"BANK A (ABC)","number":"11 1111 2222 3333"}}',
                Config::INVOICE_NUMBER_FORMAT => 'PROF NN/MM/YYYY',
            ]
        );
    }

    public function testBrowseInvoicesWhenNoInvoices(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(0);
    }

    public function testBrowseInvoicesWhenInvoicesAreDisabeld(): void
    {
        Config::updateAdminSettings([Config::INVOICE_ENABLED => '0']);

        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testBrowseInvoices(): void
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');

        factory(Invoice::class)->create(
            [
                'user_id' => $user->id,
                'buyer_name' => 'Foo co.',
                'net_amount' => 1000,
            ]
        );
        // default ref link
        factory(Invoice::class)->create(['user_id' => $user->id]);
        // deleted ref link
        factory(Invoice::class)->create(['user_id' => $user->id, 'deleted_at' => now()]);
        // other user ref link
        factory(Invoice::class)->create();

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(2);
        $data = $response->json()[1];

        $this->assertEquals('Foo co.', $data['buyerName']);
        $this->assertEquals(1000, $data['netAmount']);
        $this->assertNotEmpty($data['downloadUrl']);
    }

    public function testAddInvoice(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->postJson(
            self::URI,
            [
                'invoice' => [
                    'buyerName' => 'Foo co.',
                    'buyerAddress' => 'Dummy address',
                    'buyerCountry' => 'PL',
                    'buyerVatId' => 'PL123123123',
                    'currency' => 'USD',
                    'net_amount' => 1000,
                ]
            ]
        );
        $response->assertStatus(Response::HTTP_CREATED);

        $response = $this->getJson(self::URI);
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonCount(1);
        Mail::assertQueued(InvoiceCreated::class);
    }

    public function testAddInvoiceValidation(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->postJson(
            self::URI,
            [
                'invoice' => [
                    'buyerName' => null,
                    'buyerAddress' => null,
                    'buyerPostalCode' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                    'buyerCountry' => 'AAA',
                    'buyerVatId' => null,
                    'currency' => 'XY',
                    'netAmount' => -345,
                ]
            ]
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $errors = $response->json()['errors'];
        $this->assertArrayHasKey('buyerName', $errors);
        $this->assertArrayHasKey('buyerAddress', $errors);
        $this->assertArrayHasKey('buyerPostalCode', $errors);
        $this->assertArrayHasKey('buyerCountry', $errors);
        $this->assertArrayHasKey('buyerVatId', $errors);
        $this->assertArrayHasKey('currency', $errors);
        $this->assertArrayHasKey('netAmount', $errors);
    }

    public function testNotSupportedCurrency(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->postJson(
            self::URI,
            [
                'invoice' => [
                    'buyerName' => 'Foo co.',
                    'buyerAddress' => 'Dummy address',
                    'buyerCountry' => 'PL',
                    'buyerVatId' => 'PL123123123',
                    'currency' => 'GBP',
                    'net_amount' => 1000,
                ]
            ]
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
