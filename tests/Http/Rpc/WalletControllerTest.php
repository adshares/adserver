<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Http\Rpc;

use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\Http\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WalletControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testCalculateWithdrawSameNode()
    {
        $this->actingAs(factory(User::class)->create(), 'api');
        $response = $this->postJson(
            '/api/calculate-withdrawal',
            [
                'amount' => 100000000000,
                'to' => '0001-00000000-XXXX',
            ]
        );

        $response
            ->assertStatus(200)
            ->assertExactJson([
                'amount' => 100000000000,
                'fee' => 50000000,
                'total' => 100050000000,
            ]);
    }

    public function testCalculateWithdrawDiffNode()
    {
        $this->actingAs(factory(User::class)->create(), 'api');
        $response = $this->postJson(
            '/api/calculate-withdrawal',
            [
                'amount' => 100000000000,
                'to' => '0002-00000000-XXXX',
            ]
        );

        $response
            ->assertStatus(200)
            ->assertExactJson([
                'amount' => 100000000000,
                'fee' => 100000000,
                'total' => 100100000000,
            ]);
    }

    public function testCalculateWithdrawInvalidAddress()
    {
        $this->actingAs(factory(User::class)->create(), 'api');
        $response = $this->postJson(
            '/api/calculate-withdrawal',
            [
                'amount' => 100000000000,
                'to' => '0002-00000000-ABCD',
            ]
        );

        $response->assertStatus(422);
    }

    public function testWithdraw()
    {
        $this->actingAs(factory(User::class)->create(), 'api');
        $response = $this->postJson(
            '/api/wallet/withdraw',
            [
                'amount' => 100000000000,
                'to' => '0001-00000000-XXXX',
            ]
        );

        $response->assertStatus(204);
    }

    public function testWithdrawInvalidAmount()
    {
        $this->actingAs(factory(User::class)->create(), 'api');
        $response = $this->postJson(
            '/api/wallet/withdraw',
            [
                'amount' => 100000000000,
                'to' => '0001-00000000-ABC',
            ]
        );
        $response->assertStatus(422);
    }

    public function testWithdrawInsufficientFunds()
    {
        $this->actingAs(factory(User::class)->create(), 'api');
        $response = $this->postJson(
            '/api/wallet/withdraw',
            [
                'amount' => 5000000000000000000,
                'to' => '0001-00000000-XXXX',
            ]
        );
        $response->assertStatus(400);
    }

    public function testDepositInfo()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        $response = $this->get('/api/deposit-info');

        $response
            ->assertStatus(200)
            ->assertJson(['address' => config('app.adshares_address')]);
        $content = json_decode($response->getContent());
        // check response field
        $this->assertObjectHasAttribute('message', $content);
        $message = $content->message;
        // check format
        $this->assertTrue((strlen($message) === 64) && ctype_xdigit($message));
        // check value
        $this->assertTrue(strpos($message, $user->uuid) !== false);
    }
}
