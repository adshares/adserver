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
use Adshares\Adserver\Models\UserLedger;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;

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
            ->assertStatus(Response::HTTP_OK)
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
            ->assertStatus(Response::HTTP_OK)
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
                'to' => '0002-00000000-ABCD',// invalid address
            ]
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCalculateWithdrawInvalidAdServerAddress()
    {
        Config::set('app.adshares_address', '');//invalid ADS address set for AdServer
        $this->actingAs(factory(User::class)->create(), 'api');
        $response = $this->postJson(
            '/api/calculate-withdrawal',
            [
                'amount' => 100000000000,
                'to' => '0002-00000000-XXXX',
            ]
        );

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testWithdraw()
    {
        $user = factory(User::class)->create();
        $this->generateUserIncome($user->id, 200000000000);
        $this->actingAs($user, 'api');
        $response = $this->postJson(
            '/api/wallet/withdraw',
            [
                'amount' => 100000000000,
                'to' => '0001-00000000-XXXX',
            ]
        );

        $response->assertStatus(Response::HTTP_NO_CONTENT);
    }

    public function testWithdrawWithMemo()
    {
        $user = factory(User::class)->create();
        $this->generateUserIncome($user->id, 200000000000);
        $this->actingAs($user, 'api');
        $response = $this->postJson(
            '/api/wallet/withdraw',
            [
                'amount' => 100000000000,
                'memo' => '00000000111111110000000011111111abcdef00111111110000000123456789',
                'to' => '0001-00000000-XXXX',
            ]
        );

        $response->assertStatus(Response::HTTP_NO_CONTENT);
    }

    public function testWithdrawInvalidAddress()
    {
        $user = factory(User::class)->create();
        $this->generateUserIncome($user->id, 200000000000);
        $this->actingAs($user, 'api');
        $response = $this->postJson(
            '/api/wallet/withdraw',
            [
                'amount' => 100000000000,
                'to' => '0001-00000000-ABC',// invalid address
            ]
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testWithdrawInvalidMemo()
    {
        $user = factory(User::class)->create();
        $this->generateUserIncome($user->id, 200000000000);
        $this->actingAs($user, 'api');
        $response = $this->postJson(
            '/api/wallet/withdraw',
            [
                'amount' => 100000000000,
                'memo' => 'hello',
                'to' => '0001-00000000-ABC',// invalid address
            ]
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testWithdrawInvalidAdServerAddress()
    {
        Config::set('app.adshares_address', '');//invalid ASD address set for AdServer
        $user = factory(User::class)->create();
        $this->generateUserIncome($user->id, 200000000000);
        $this->actingAs($user, 'api');
        $response = $this->postJson(
            '/api/wallet/withdraw',
            [
                'amount' => 100000000000,
                'to' => '0001-00000000-XXXX',
            ]
        );

        $response->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testWithdrawInsufficientFunds()
    {
        $user = factory(User::class)->create();
        $this->generateUserIncome($user->id, 200000000000);
        $this->actingAs($user, 'api');
        $response = $this->postJson(
            '/api/wallet/withdraw',
            [
                'amount' => 200000000001,
                'to' => '0001-00000000-XXXX',
            ]
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testDepositInfo()
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        $response = $this->get('/api/deposit-info');

        $response
            ->assertStatus(Response::HTTP_OK)
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

    private function generateUserIncome(int $userId, int $amount)
    {
        $ul = new UserLedger;
        $ul->users_id = $userId;
        $ul->amount = $amount;
        $ul->desc = '0001:0000000A:0001';
        $ul->save();
    }
}
