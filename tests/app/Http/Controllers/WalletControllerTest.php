<?php
/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

// phpcs:ignoreFile PHPCompatibility.Miscellaneous.ValidIntegers.HexNumericStringFound

declare(strict_types=1);

namespace Adshares\Adserver\Tests\Http\Controllers;

use Adshares\Adserver\Jobs\AdsSendOne;
use Adshares\Adserver\Mail\WalletConnectConfirm;
use Adshares\Adserver\Mail\WalletConnected;
use Adshares\Adserver\Mail\WithdrawalApproval;
use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpFoundation\Response;

class WalletControllerTest extends TestCase
{
    private const CONNECT_INIT_URI = '/api/wallet/connect/init';
    private const CONNECT_URI = '/api/wallet/connect';
    private const CONNECT_CONFIRM_URI = '/api/wallet/connect/confirm';

    public function testCalculateWithdrawSameNode(): void
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        $this->generateUserIncome($user->id, 500000000000);

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
                'receive' => 100000000000,
            ]
        );
    }

    public function testCalculateWithdrawDiffNode(): void
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        $this->generateUserIncome($user->id, 500000000000);

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
                'receive' => 100000000000,
            ]
        );
    }

    public function testCalculateWithdrawMaxAmount(): void
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        $this->generateUserIncome($user->id, 50000000000);

        $response = $this->postJson(
            '/api/calculate-withdrawal',
            [
                'to' => '0001-00000000-XXXX',
            ]
        );

        $response->assertStatus(Response::HTTP_OK)->assertExactJson(
            [
                'amount' => 49975012494,
                'fee' => 24987506,
                'total' => 50000000000,
                'receive' => 49975012494,
            ]
        );
    }

    public function testCalculateWithdrawOverBalance(): void
    {
        $user = factory(User::class)->create();
        $this->actingAs($user, 'api');
        $this->generateUserIncome($user->id, 50000000000);

        $response = $this->postJson(
            '/api/calculate-withdrawal',
            [
                'amount' => 100000000000,
                'to' => '0001-00000000-XXXX',
            ]
        );

        $response->assertStatus(Response::HTTP_OK)->assertExactJson(
            [
                'amount' => 49975012494,
                'fee' => 24987506,
                'total' => 50000000000,
                'receive' => 49975012494,
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

    public function testCalculateWithdrawAdsWallet(): void
    {
        $user = factory(User::class)->create([
            'email' => null,
            'wallet_address' => new WalletAddress(WalletAddress::NETWORK_ADS, '0001-00000001-8B4E')
        ]);
        $this->actingAs($user, 'api');
        $this->generateUserIncome($user->id, 500000000000);
        $response = $this->postJson(
            '/api/calculate-withdrawal',
            [
                'amount' => 100000000000,
            ]
        );
        $response->assertStatus(Response::HTTP_OK)->assertExactJson(
            [
                'amount' => 100000000000,
                'fee' => 50000000,
                'total' => 100050000000,
                'receive' => 100000000000,
            ]
        );
    }

    public function testCalculateWithdrawBscWallet(): void
    {
        $user = factory(User::class)->create([
            'email' => null,
            'wallet_address' => new WalletAddress(
                WalletAddress::NETWORK_BSC,
                '0xace8d624e8c12c0a16df4a61dee85b0fd3f94ceb'
            )
        ]);
        $this->actingAs($user, 'api');
        $this->generateUserIncome($user->id, 500000000000);
        $response = $this->postJson(
            '/api/calculate-withdrawal',
            [
                'amount' => 100000000000,
            ]
        );
        $response->assertStatus(Response::HTTP_OK)->assertExactJson(
            [
                'amount' => 100000000000,
                'fee' => 50000000,
                'total' => 100050000000,
                'receive' => 99999900000,
            ]
        );
    }

    public function testCalculateWithdrawUnsupportedWalletNetwork(): void
    {
        $user = factory(User::class)->create([
            'email' => null,
            'wallet_address' => new WalletAddress(
                WalletAddress::NETWORK_BTC,
                '3ALP7JRzHAyrhX5LLPSxU1A9duDiGbnaKg'
            )
        ]);
        $this->actingAs($user, 'api');
        $this->generateUserIncome($user->id, 500000000000);
        $response = $this->postJson(
            '/api/calculate-withdrawal',
            [
                'amount' => 100000000000,
            ]
        );
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testWithdrawApprovalMail(): void
    {
        Mail::fake();
        Queue::fake();

        $user = factory(User::class)->create(['email_confirmed_at' => now(), 'admin_confirmed_at' => now()]);
        $this->generateUserIncome($user->id, 200000000000);

        $this->actingAs($user, 'api');

        $amount = 100000000000;
        $response = $this->postJson(
            '/api/wallet/withdraw',
            [
                'amount' => $amount,
                'to' => '0001-00000000-XXXX',
            ]
        );

        $response->assertStatus(Response::HTTP_NO_CONTENT);
        $tokens = Token::all();
        self::assertCount(1, $tokens);
        Mail::assertQueued(WithdrawalApproval::class);
        Queue::assertNotPushed(AdsSendOne::class);

        $firstToken = $tokens->first();
        $userLedgerEntry = UserLedgerEntry::find($firstToken->payload['ledgerEntry']);

        self::assertSame(UserLedgerEntry::STATUS_AWAITING_APPROVAL, $userLedgerEntry->status);

        $response2 = $this->postJson(
            '/api/wallet/confirm-withdrawal',
            [
                'token' => $firstToken->uuid,
            ]
        );

        $response2->assertStatus(Response::HTTP_OK);
        self::assertSame(UserLedgerEntry::STATUS_PENDING, UserLedgerEntry::find($userLedgerEntry->id)->status);
        Queue::assertPushed(AdsSendOne::class, 1);
    }

    public function testWithdrawAdsWallet(): void
    {
        Mail::fake();
        Queue::fake();

        $user = factory(User::class)->create([
            'email_confirmed_at' => now(),
            'admin_confirmed_at' => now(),
            'email' => null,
            'wallet_address' => new WalletAddress(WalletAddress::NETWORK_ADS, '0001-00000001-8B4E')
        ]);
        $this->actingAs($user, 'api');
        $this->generateUserIncome($user->id, 200000000000);

        $this->actingAs($user, 'api');

        $amount = 100000000000;
        $response = $this->postJson('/api/wallet/withdraw', ['amount' => $amount]);

        $response->assertStatus(Response::HTTP_NO_CONTENT);
        $tokens = Token::all();
        self::assertCount(0, $tokens);
        Mail::assertNothingQueued();

        $userLedgerEntry = UserLedgerEntry::where(['type' => UserLedgerEntry::TYPE_WITHDRAWAL])->first();
        $this->assertNotNull($userLedgerEntry);
        $this->assertEquals(UserLedgerEntry::STATUS_PENDING, $userLedgerEntry->status);
        $this->assertEquals(-100050000000, $userLedgerEntry->amount);
        Queue::assertPushed(AdsSendOne::class, 1);
    }

    public function testWithdrawBscWallet(): void
    {
        Mail::fake();
        Queue::fake();

        $user = factory(User::class)->create([
            'email_confirmed_at' => now(),
            'admin_confirmed_at' => now(),
            'email' => null,
            'wallet_address' => new WalletAddress(
                WalletAddress::NETWORK_BSC,
                '0xace8d624e8c12c0a16df4a61dee85b0fd3f94ceb'
            )
        ]);
        $this->actingAs($user, 'api');
        $this->generateUserIncome($user->id, 200000000000);

        $amount = 100000000000;
        $response = $this->postJson('/api/wallet/withdraw', ['amount' => $amount]);

        $response->assertStatus(Response::HTTP_NO_CONTENT);
        $tokens = Token::all();
        self::assertCount(0, $tokens);
        Mail::assertNothingQueued();

        $userLedgerEntry = UserLedgerEntry::where(['type' => UserLedgerEntry::TYPE_WITHDRAWAL])->first();
        $this->assertNotNull($userLedgerEntry);
        $this->assertEquals(UserLedgerEntry::STATUS_PENDING, $userLedgerEntry->status);
        $this->assertEquals(-100050000000, $userLedgerEntry->amount);
        Queue::assertPushed(AdsSendOne::class, 1);
    }

    public function testWithdrawUnsupportedWalletNetwork(): void
    {
        Mail::fake();
        Queue::fake();

        $user = factory(User::class)->create([
            'email_confirmed_at' => now(),
            'admin_confirmed_at' => now(),
            'email' => null,
            'wallet_address' => new WalletAddress(
                WalletAddress::NETWORK_BTC,
                '3ALP7JRzHAyrhX5LLPSxU1A9duDiGbnaKg'
            )
        ]);
        $this->actingAs($user, 'api');
        $this->generateUserIncome($user->id, 200000000000);

        $amount = 100000000000;
        $response = $this->postJson('/api/wallet/withdraw', ['amount' => $amount]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testWithdrawReject(): void
    {
        Mail::fake();

        $user = factory(User::class)->create(['email_confirmed_at' => now(), 'admin_confirmed_at' => now()]);
        $this->generateUserIncome($user->id, 200000000000);

        $this->actingAs($user, 'api');

        $amount = 100000000000;
        $response = $this->postJson(
            '/api/wallet/withdraw',
            [
                'amount' => $amount,
                'to' => '0001-00000000-XXXX',
            ]
        );

        $response->assertStatus(Response::HTTP_NO_CONTENT);
        $tokens = Token::all();
        self::assertCount(1, $tokens);
        Mail::assertQueued(WithdrawalApproval::class);

        $firstToken = $tokens->first();
        $userLedgerEntry = UserLedgerEntry::find($firstToken->payload['ledgerEntry']);

        self::assertSame(UserLedgerEntry::STATUS_AWAITING_APPROVAL, $userLedgerEntry->status);

        $this->delete(
            sprintf('/api/wallet/cancel-withdrawal/%d', $userLedgerEntry->id)
        )->assertStatus(Response::HTTP_OK);

        self::assertSame(UserLedgerEntry::STATUS_CANCELED, UserLedgerEntry::find($userLedgerEntry->id)->status);
    }

    public function testWithdrawCancelInvalidLedgerEntry(): void
    {
        $user = factory(User::class)->create();
        $this->generateUserIncome($user->id, 200000000000);

        $this->actingAs($user, 'api');

        $this->delete(
            sprintf('/api/wallet/cancel-withdrawal/%d', 1)
        )->assertStatus(Response::HTTP_NOT_FOUND);
    }

    public function testWithdrawWithMemo(): void
    {
        $user = factory(User::class)->create(['email_confirmed_at' => now(), 'admin_confirmed_at' => now()]);
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

        self::assertCount(1, Token::all());

        $response->assertStatus(Response::HTTP_NO_CONTENT);
    }

    public function testWithdrawNoConfirmed(): void
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

        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testWithdrawInvalidAddress(): void
    {
        $user = factory(User::class)->create(['email_confirmed_at' => now(), 'admin_confirmed_at' => now()]);
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
        $user = factory(User::class)->create(['email_confirmed_at' => now(), 'admin_confirmed_at' => now()]);
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

    public function testWithdrawInsufficientFunds(): void
    {
        $user = factory(User::class)->create(['email_confirmed_at' => now(), 'admin_confirmed_at' => now()]);
        $amount = 20 * (10 ** 11);
        $this->generateUserIncome($user->id, $amount);
        $this->actingAs($user, 'api');
        $response = $this->postJson(
            '/api/wallet/withdraw',
            [
                'amount' => $amount + 1,
                'to' => '0001-00000000-XXXX',
            ]
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testWithdrawInvalidAmount(): void
    {
        $user = factory(User::class)->create(['email_confirmed_at' => now(), 'admin_confirmed_at' => now()]);
        $this->actingAs($user, 'api');
        $response = $this->postJson(
            '/api/wallet/withdraw',
            [
                'amount' => -10,
                'to' => '0001-00000000-XXXX',
            ]
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
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
        $this->assertNotFalse(strpos($message, $user->uuid));
    }

    public function testHistory(): void
    {
        $user = factory(User::class)->create();
        $userId = $user->id;

        $amountInClicks = 200000000000;
        $this->initUserLedger($userId, $amountInClicks);

        $this->actingAs($user, 'api');
        $response = $this->getJson('/api/wallet/history');

        $response->assertStatus(Response::HTTP_OK)
            ->assertExactJson(
                [
                    'limit' => 10,
                    'offset' => 0,
                    'itemsCount' => 2,
                    'itemsCountAll' => 2,
                    'items' => [
                        [
                            'amount' => -$amountInClicks,
                            'status' => UserLedgerEntry::STATUS_ACCEPTED,
                            'type' => UserLedgerEntry::TYPE_WITHDRAWAL,
                            'date' => '2018-10-24T15:20:49+00:00',
                            'address' => '0001-00000000-XXXX',
                            'txid' => null,
                            'id' => 2,
                        ],
                        [
                            'amount' => $amountInClicks,
                            'status' => UserLedgerEntry::STATUS_ACCEPTED,
                            'type' => UserLedgerEntry::TYPE_DEPOSIT,
                            'date' => '2018-10-24T15:00:49+00:00',
                            'address' => '0001-00000000-XXXX',
                            'txid' => '0001:0000000A:0001',
                            'id' => 1,
                        ],
                    ],
                ]
            );
    }

    private function initUserLedger($userId, $amountInClicks): void
    {
        // add entry with a txid
        $this->generateUserIncome($userId, $amountInClicks);
        // add entry without txid
        $dateString = '2018-10-24 15:20:49';
        $ul = new UserLedgerEntry();
        $ul->id = 2;
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

        $response->assertStatus(Response::HTTP_OK)->assertExactJson(
            [
                'limit' => 1,
                'offset' => 0,
                'itemsCount' => 1,
                'itemsCountAll' => 2,
                'items' => [
                    [
                        'amount' => -$amountInClicks,
                        'status' => UserLedgerEntry::STATUS_ACCEPTED,
                        'type' => UserLedgerEntry::TYPE_WITHDRAWAL,
                        'date' => '2018-10-24T15:20:49+00:00',
                        'address' => '0001-00000000-XXXX',
                        'txid' => null,
                        'id' => 2,
                    ],
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

        $response->assertStatus(Response::HTTP_OK)->assertExactJson(
            [
                'limit' => 1,
                'offset' => 1,
                'itemsCount' => 1,
                'itemsCountAll' => 2,
                'items' => [
                    [
                        'amount' => $amountInClicks,
                        'status' => UserLedgerEntry::STATUS_ACCEPTED,
                        'type' => UserLedgerEntry::TYPE_DEPOSIT,
                        'date' => '2018-10-24T15:00:49+00:00',
                        'address' => '0001-00000000-XXXX',
                        'txid' => '0001:0000000A:0001',
                        'id' => 1,
                    ],
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

    public function testHistoryTypes(): void
    {
        $user = factory(User::class)->create();
        $userId = $user->id;

        $amountInClicks = 200000000000;
        $this->initUserLedger($userId, $amountInClicks);

        $this->actingAs($user, 'api');
        $response = $this->getJson('/api/wallet/history?types[]=' . UserLedgerEntry::TYPE_DEPOSIT);

        $response->assertStatus(Response::HTTP_OK)
            ->assertExactJson(
                [
                    'limit' => 10,
                    'offset' => 0,
                    'itemsCount' => 1,
                    'itemsCountAll' => 1,
                    'items' => [
                        [
                            'amount' => $amountInClicks,
                            'status' => UserLedgerEntry::STATUS_ACCEPTED,
                            'type' => UserLedgerEntry::TYPE_DEPOSIT,
                            'date' => '2018-10-24T15:00:49+00:00',
                            'address' => '0001-00000000-XXXX',
                            'txid' => '0001:0000000A:0001',
                            'id' => 1,
                        ],
                    ],
                ]
            );
    }

    public function testHistoryTypesInvalid(): void
    {
        $user = factory(User::class)->create();
        $userId = $user->id;

        $amountInClicks = 200000000000;
        $this->initUserLedger($userId, $amountInClicks);

        $this->actingAs($user, 'api');
        $response = $this->getJson('/api/wallet/history?types[]=100');

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testHistoryDates(): void
    {
        $user = factory(User::class)->create();
        $userId = $user->id;

        $amountInClicks = 200000000000;
        $this->initUserLedger($userId, $amountInClicks);

        $this->actingAs($user, 'api');
        $response = $this->getJson(
            '/api/wallet/history?date_from=2018-10-24T15:20:49%2B00:00&date_to=2019-10-24T15:20:49%2B00:00'
        );

        $response->assertStatus(Response::HTTP_OK)
            ->assertExactJson(
                [
                    'limit' => 10,
                    'offset' => 0,
                    'itemsCount' => 1,
                    'itemsCountAll' => 1,
                    'items' => [
                        [
                            'amount' => -$amountInClicks,
                            'status' => UserLedgerEntry::STATUS_ACCEPTED,
                            'type' => UserLedgerEntry::TYPE_WITHDRAWAL,
                            'date' => '2018-10-24T15:20:49+00:00',
                            'address' => '0001-00000000-XXXX',
                            'txid' => null,
                            'id' => 2,
                        ],
                    ],
                ]
            );
    }

    public function testHistoryDatesInvalid(): void
    {
        $user = factory(User::class)->create();
        $userId = $user->id;

        $amountInClicks = 200000000000;
        $this->initUserLedger($userId, $amountInClicks);

        $this->actingAs($user, 'api');
        $response = $this->getJson('/api/wallet/history?date_from=2018-10-24&date_to=2019-10-24');

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testConnectInit(): void
    {
        $this->login();
        $response = $this->get(self::CONNECT_INIT_URI);
        $response->assertStatus(Response::HTTP_OK)->assertJsonStructure([
            'message',
            'token',
            'gateways' => ['bsc']
        ]);
        self::assertCount(1, Token::all());
    }

    public function testAdsConnect(): void
    {
        $user = $this->login();
        $message = '123abc';
        $token = Token::generate(Token::WALLET_CONNECT, $user, [
            'request' => [],
            'message' => $message,
        ])->uuid;

        //SK: CA978112CA1BBDCAFAC231B39A23DC4DA786EFF8147C4E72B9807785AFEE48BB
        //PK: EAE1C8793B5597C4B3F490E76AC31172C439690F8EE14142BB851A61F9A49F0E
        //message:123abc
        $sign = '0x72d877601db72b6d843f11d634447bbdd836de7adbd5b2dfc4fa718ea68e7b18d65547b1265fec0c121ac76dfb086806da393d244dec76d72f49895f48aa5a01';
        $response = $this->patch(self::CONNECT_URI, [
            'token' => $token,
            'network' => 'ads',
            'address' => '0001-00000001-8B4E',
            'signature' => $sign,
            'uri' => '/auth/wallet-confirm/',
        ]);
        $response->assertStatus(Response::HTTP_NO_CONTENT);

        Mail::assertQueued(WalletConnectConfirm::class);
        self::assertCount(1, Token::all());
    }

    public function testBscConnect(): void
    {
        $user = $this->login();
        $message = '123abc';
        $token = Token::generate(Token::WALLET_CONNECT, $user, [
            'request' => [],
            'message' => $message,
        ])->uuid;

        //message:123abc
        $sign = '0xe649d27a045e5a9397a9a7572d93471e58f6ab8d024063b2ea5b6bcb4f65b5eb4aecf499197f71af91f57cd712799d2a559e3a3a40243db2c4e947aeb0a2c8181b';
        $response = $this->patch(self::CONNECT_URI, [
            'token' => $token,
            'network' => 'bsc',
            'address' => '0x79e51bA0407bEc3f1246797462EaF46850294301',
            'signature' => $sign,
            'uri' => '/auth/wallet-confirm/',
        ]);
        $response->assertStatus(Response::HTTP_NO_CONTENT);

        Mail::assertQueued(WalletConnectConfirm::class);
        self::assertCount(1, Token::all());
    }

    public function testConnectChangeAddress(): void
    {
        $user = $this->login(factory(User::class)->create([
            'wallet_address' => WalletAddress::fromString('ads:0001-00000001-8B4E'),
            'email' => null,
        ]));
        $message = '123abc';
        $token = Token::generate(Token::WALLET_CONNECT, $user, [
            'request' => [],
            'message' => $message,
        ])->uuid;

        //message:123abc
        $sign = '0xe649d27a045e5a9397a9a7572d93471e58f6ab8d024063b2ea5b6bcb4f65b5eb4aecf499197f71af91f57cd712799d2a559e3a3a40243db2c4e947aeb0a2c8181b';
        $response = $this->patch(self::CONNECT_URI, [
            'token' => $token,
            'network' => 'bsc',
            'address' => '0x79e51bA0407bEc3f1246797462EaF46850294301',
            'signature' => $sign
        ]);
        $response->assertStatus(Response::HTTP_OK)->assertJsonStructure([
            'id',
            'email',
            'adserverWallet' => ['walletAddress', 'walletNetwork']
        ]);

        Mail::assertNotQueued(WalletConnected::class);

        /** @var User $userDb */
        $userDb = User::fetchById($user->id);
        $this->assertNotNull($userDb->wallet_address);
        $this->assertEquals(WalletAddress::NETWORK_BSC, $userDb->wallet_address->getNetwork());
        $this->assertEquals('0x79e51ba0407bec3f1246797462eaf46850294301', $userDb->wallet_address->getAddress());
    }

    public function testConnectWithOverwrite(): void
    {
        factory(User::class)->create([
            'wallet_address' => WalletAddress::fromString('ads:0001-00000001-8B4E')
        ]);

        $user = $this->login();
        $message = '123abc';
        $token = Token::generate(Token::WALLET_CONNECT, $user, [
            'request' => [],
            'message' => $message,
        ])->uuid;

        //SK: CA978112CA1BBDCAFAC231B39A23DC4DA786EFF8147C4E72B9807785AFEE48BB
        //PK: EAE1C8793B5597C4B3F490E76AC31172C439690F8EE14142BB851A61F9A49F0E
        //message:123abc
        $sign = '0x72d877601db72b6d843f11d634447bbdd836de7adbd5b2dfc4fa718ea68e7b18d65547b1265fec0c121ac76dfb086806da393d244dec76d72f49895f48aa5a01';
        $response = $this->patch(self::CONNECT_URI, [
            'token' => $token,
            'network' => 'ads',
            'address' => '0001-00000001-8B4E',
            'signature' => $sign
        ]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testInvalidConnectSignature(): void
    {
        $user = $this->login();
        $message = '123abc';
        $token = Token::generate(Token::WALLET_CONNECT, $user, [
            'request' => [],
            'message' => $message,
        ])->uuid;

        $response = $this->patch(self::CONNECT_URI, [
            'token' => $token,
            'network' => 'ads',
            'address' => '0001-00000001-8B4E',
            'signature' => '0x1231231231'
        ]);
        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testInvalidConnectToken(): void
    {
        $this->login();
        $sign = '0x72d877601db72b6d843f11d634447bbdd836de7adbd5b2dfc4fa718ea68e7b18d65547b1265fec0c121ac76dfb086806da393d244dec76d72f49895f48aa5a01';
        $response = $this->patch(self::CONNECT_URI, [
            'token' => 'foo_token',
            'network' => 'ads',
            'address' => '0001-00000001-8B4E',
            'signature' => $sign
        ]);

        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testNonExistedConnectToken(): void
    {
        $this->login();
        $sign = '0x72d877601db72b6d843f11d634447bbdd836de7adbd5b2dfc4fa718ea68e7b18d65547b1265fec0c121ac76dfb086806da393d244dec76d72f49895f48aa5a01';
        $response = $this->patch(self::CONNECT_URI, [
            'token' => '1231231231',
            'network' => 'ads',
            'address' => '0001-00000001-8B4E',
            'signature' => $sign
        ]);

        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testExpiredConnectToken(): void
    {
        $user = $this->login();
        $message = '123abc';
        $token = Token::generate(Token::WALLET_CONNECT, $user, [
            'request' => [],
            'message' => $message,
        ]);
        $token->valid_until = '2020-01-01 12:00:00';
        $token->saveOrFail();

        $sign = '0x72d877601db72b6d843f11d634447bbdd836de7adbd5b2dfc4fa718ea68e7b18d65547b1265fec0c121ac76dfb086806da393d244dec76d72f49895f48aa5a01';
        $response = $this->patch(self::CONNECT_URI, [
            'token' => $token->uuid,
            'network' => 'ads',
            'address' => '0001-00000001-8B4E',
            'signature' => $sign
        ]);
        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testInvalidConnectUser(): void
    {
        $this->login();
        $message = '123abc';
        $token = Token::generate(Token::WALLET_CONNECT, factory(User::class)->create(), [
            'request' => [],
            'message' => $message,
        ]);

        $sign = '0x72d877601db72b6d843f11d634447bbdd836de7adbd5b2dfc4fa718ea68e7b18d65547b1265fec0c121ac76dfb086806da393d244dec76d72f49895f48aa5a01';
        $response = $this->patch(self::CONNECT_URI, [
            'token' => $token->uuid,
            'network' => 'ads',
            'address' => '0001-00000001-8B4E',
            'signature' => $sign
        ]);
        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testConnectConfirm(): void
    {
        $user = $this->login();
        $token = Token::generate(Token::WALLET_CONNECT_CONFIRM, $user, [
            'wallet_address' => 'ads:0001-00000001-8B4E',
        ]);

        $response = $this->post(self::CONNECT_CONFIRM_URI . '/' . $token->uuid);
        $response->assertStatus(Response::HTTP_OK)->assertJsonStructure([
            'id',
            'email',
            'adserverWallet' => ['walletAddress', 'walletNetwork']
        ]);

        Mail::assertQueued(WalletConnected::class);
        self::assertEmpty(Token::all());

        /** @var User $userDb */
        $userDb = User::fetchById($user->id);
        $this->assertNotNull($userDb->wallet_address);
        $this->assertEquals(WalletAddress::NETWORK_ADS, $userDb->wallet_address->getNetwork());
        $this->assertEquals('0001-00000001-8B4E', $userDb->wallet_address->getAddress());
    }

    public function testConnectConfirmWithOverwrite(): void
    {
        factory(User::class)->create([
            'wallet_address' => WalletAddress::fromString('ads:0001-00000001-8B4E')
        ]);

        $user = $this->login();
        $token = Token::generate(Token::WALLET_CONNECT_CONFIRM, $user, [
            'wallet_address' => 'ads:0001-00000001-8B4E',
        ]);

        $response = $this->post(self::CONNECT_CONFIRM_URI . '/' . $token->uuid);
        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testInvalidConnectConfirmToken(): void
    {
        $this->login();
        $response = $this->post(self::CONNECT_CONFIRM_URI . '/foo');
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testNonExistedConnectConfirmToken(): void
    {
        $this->login();
        $response = $this->post(self::CONNECT_CONFIRM_URI . '/1231231231');
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testExpiredConnectConfirmToken(): void
    {
        $user = $this->login();
        $token = Token::generate(Token::WALLET_CONNECT_CONFIRM, $user, [
            'wallet_address' => 'ads:0001-00000001-8B4E',
        ]);
        $token->valid_until = '2020-01-01 12:00:00';
        $token->saveOrFail();

        $response = $this->post(self::CONNECT_CONFIRM_URI . '/' . $token->uuid);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testInvalidConnectConfirmUser(): void
    {
        $this->login();
        $token = Token::generate(Token::WALLET_CONNECT_CONFIRM, factory(User::class)->create(), [
            'wallet_address' => 'ads:0001-00000001-8B4E',
        ]);

        $response = $this->post(self::CONNECT_CONFIRM_URI . '/' . $token->uuid);
        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testInvalidConnectConfirmAddress(): void
    {
        $user = $this->login();
        $token = Token::generate(Token::WALLET_CONNECT_CONFIRM, $user, [
            'wallet_address' => 'foo:xyz',
        ]);

        $response = $this->post(self::CONNECT_CONFIRM_URI . '/' . $token->uuid);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function generateUserIncome(int $userId, int $amount): void
    {
        $dateString = '2018-10-24 15:00:49';

        $ul = new UserLedgerEntry();
        $ul->id = 1;
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
}
