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

namespace Adshares\Adserver\Tests\Http;

use Adshares\Adserver\Jobs\AdsSendOne;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;

class WalletControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testCalculateWithdrawSameNode(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');
        $response = $this->postJson(
            '/api/calculate-withdrawal',
            [
                'amount' => 100000000000,
                'to' => '0001-00000000-XXXX',
            ]
        );

        $response->assertStatus(Response::HTTP_OK)->assertExactJson(
            [
                'amount' => 100000000000,
                'fee' => 50000000,
                'total' => 100050000000,
            ]
        );
    }

    public function testCalculateWithdrawDiffNode(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');
        $response = $this->postJson(
            '/api/calculate-withdrawal',
            [
                'amount' => 100000000000,
                'to' => '0002-00000000-XXXX',
            ]
        );

        $response->assertStatus(Response::HTTP_OK)->assertExactJson(
            [
                'amount' => 100000000000,
                'fee' => 100000000,
                'total' => 100100000000,
            ]
        );
    }

    public function testCalculateWithdrawInvalidAddress(): void
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

    public function testCalculateWithdrawInvalidAdServerAddress(): void
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

    public function testWithdraw(): void
    {
        $this->expectsJobs(AdsSendOne::class);

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

    private function generateUserIncome(int $userId, int $amount): void
    {
        $dateString = '2018-10-24 15:00:49';

        $ul = new UserLedgerEntry();
        $ul->user_id = $userId;
        $ul->amount = $amount;
        $ul->address_from = '0001-00000000-XXXX';
        $ul->address_to = '0001-00000000-XXXX';
        $ul->txid = '0001:0000000A:0001';
        $ul->type = UserLedgerEntry::TYPE_DEPOSIT;
        $ul->setCreatedAt($dateString);
        $ul->setUpdatedAt($dateString);
        $ul->save();
    }

    public function testWithdrawWithMemo(): void
    {
        $this->expectsJobs(AdsSendOne::class);

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

    public function testWithdrawInvalidAddress(): void
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

    public function testWithdrawInvalidMemo(): void
    {
        $user = factory(User::class)->create();
        $this->generateUserIncome($user->id, 200000000000);
        $this->actingAs($user, 'api');
        $response = $this->postJson(
            '/api/wallet/withdraw',
            [
                'amount' => 100000000000,
                'memo' => 'hello',// invalid memo
                'to' => '0001-00000000-XXXX',
            ]
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testWithdrawInvalidAdServerAddress(): void
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

    public function testWithdrawInsufficientFunds(): void
    {
        $this->expectsJobs(AdsSendOne::class);

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

        // balance check was moved to job, so controller returns success
        $response->assertStatus(Response::HTTP_NO_CONTENT);
    }

    public function testDepositInfo(): void
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        $response = $this->get('/api/deposit-info');

        $response->assertStatus(Response::HTTP_OK)->assertJson(['address' => config('app.adshares_address')]);
        $content = json_decode($response->getContent());
        // check response field
        $this->assertObjectHasAttribute('message', $content);
        $message = $content->message;
        // check format
        $this->assertTrue((strlen($message) === 64) && ctype_xdigit($message));
        // check value
        $this->assertTrue(strpos($message, $user->uuid) !== false);
    }

    public function testHistory(): void
    {
        $user = factory(User::class)->create();
        $userId = $user->id;

        $amountInClicks = 200000000000;
        $this->initUserLedger($userId, $amountInClicks);

        $this->actingAs($user, 'api');
        $response = $this->getJson('/api/wallet/history');

        $response->assertStatus(Response::HTTP_OK)->assertJson(
            [
                [
                    'amount' => $amountInClicks,
                    'status' => UserLedgerEntry::STATUS_ACCEPTED,
                    'type' => UserLedgerEntry::TYPE_DEPOSIT,
                    'date' => 'Wed, 24 Oct 2018 15:00:49 GMT',
                    'address' => '0001-00000000-XXXX',
                    'link' => 'https://operator1.e11.click/blockexplorer/transactions/0001:0000000A:0001',
                ],
                [
                    'amount' => -$amountInClicks,
                    'status' => UserLedgerEntry::STATUS_ACCEPTED,
                    'type' => UserLedgerEntry::TYPE_WITHDRAWAL,
                    'date' => 'Wed, 24 Oct 2018 15:00:49 GMT',
                    'address' => '0001-00000000-XXXX',
                    'link' => '-',
                ],
            ]
        );
    }

    private function initUserLedger($userId, $amountInClicks): void
    {
        // add entry with a txid
        $this->generateUserIncome($userId, $amountInClicks);
        // add entry without txid
        $dateString = '2018-10-24 15:00:49';
        $ul = new UserLedgerEntry();
        $ul->user_id = $userId;
        $ul->amount = -$amountInClicks;
        $ul->address_from = '0001-00000000-XXXX';
        $ul->address_to = '0001-00000000-XXXX';
        $ul->type = UserLedgerEntry::TYPE_WITHDRAWAL;
        $ul->setCreatedAt($dateString);
        $ul->setUpdatedAt($dateString);
        $ul->save();
    }

    public function testHistoryLimit(): void
    {
        $user = factory(User::class)->create();
        $userId = $user->id;

        $amountInClicks = 200000000000;
        $this->initUserLedger($userId, $amountInClicks);

        $this->actingAs($user, 'api');
        $response = $this->getJson('/api/wallet/history?limit=1');

        $response->assertStatus(Response::HTTP_OK)->assertJson(
            [
                [
                    'amount' => $amountInClicks,
                    'status' => UserLedgerEntry::STATUS_ACCEPTED,
                    'type' => UserLedgerEntry::TYPE_DEPOSIT,
                    'date' => 'Wed, 24 Oct 2018 15:00:49 GMT',
                    'address' => '0001-00000000-XXXX',
                    'link' => 'https://operator1.e11.click/blockexplorer/transactions/0001:0000000A:0001',
                ],
            ]
        );
    }

    public function testHistoryLimitInvalid(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');
        $response = $this->getJson('/api/wallet/history?limit=0');

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testHistoryOffset(): void
    {
        $user = factory(User::class)->create();
        $userId = $user->id;

        $amountInClicks = 200000000000;
        $this->initUserLedger($userId, $amountInClicks);

        $this->actingAs($user, 'api');
        $response = $this->getJson('/api/wallet/history?limit=1&offset=1');

        $response->assertStatus(Response::HTTP_OK)->assertJson(
            [
                [
                    'amount' => -$amountInClicks,
                    'status' => UserLedgerEntry::STATUS_ACCEPTED,
                    'type' => UserLedgerEntry::TYPE_WITHDRAWAL,
                    'date' => 'Wed, 24 Oct 2018 15:00:49 GMT',
                    'address' => '0001-00000000-XXXX',
                    'link' => '-',
                ],
            ]
        );
    }

    public function testHistoryOffsetInvalid(): void
    {
        $this->actingAs(factory(User::class)->create(), 'api');
        $response = $this->getJson('/api/wallet/history?limit=1&offset=-1');

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
