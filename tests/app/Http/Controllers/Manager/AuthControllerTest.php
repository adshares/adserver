<?php
// phpcs:ignoreFile PHPCompatibility.Miscellaneous.ValidIntegers.HexNumericStringFound

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

namespace Adshares\Adserver\Tests\Http\Controllers\Manager;

use Adshares\Adserver\Mail\UserConfirmed;
use Adshares\Adserver\Mail\UserEmailActivate;
use Adshares\Adserver\Mail\UserEmailChangeConfirm1Old;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\RefLink;
use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Application\Service\ExchangeRateRepository;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Config\RegistrationMode;
use Adshares\Mock\Client\DummyExchangeRateRepository;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

class AuthControllerTest extends TestCase
{
    private const CHECK_URI = '/auth/check';
    private const SELF_URI = '/auth/self';
    private const EMAIL_URI = '/auth/email';
    private const WALLET_LOGIN_INIT_URI = '/auth/login/wallet/init';
    private const WALLET_LOGIN_URI = '/auth/login/wallet';

    private const STRUCTURE_CHECK = [
        'uuid',
        'email',
        'isAdvertiser',
        'isPublisher',
        'isAdmin',
        'adserverWallet' => [
            'totalFunds',
            'totalFundsInCurrency',
            'totalFundsChange',
            'bonusBalance',
            'walletBalance',
            'walletAddress',
            'walletNetwork',
            'lastPaymentAt',
            'isAutoWithdrawal',
            'autoWithdrawalLimit',
        ],
        'isEmailConfirmed',
        'isConfirmed',
        'exchangeRate' => [
            'validAt',
            'value',
            'currency',
        ],
    ];

    public function setUp(): void
    {
        parent::setUp();
        Config::updateAdminSettings([Config::EMAIL_VERIFICATION_REQUIRED => '1']);
        Config::updateAdminSettings([Config::AUTO_CONFIRMATION_ENABLED => '1']);
        Config::updateAdminSettings([Config::REGISTRATION_MODE => RegistrationMode::PUBLIC]);
    }

    public function testPublicRegister(): void
    {
        $user = $this->registerUser();
        Mail::assertQueued(UserEmailActivate::class);
        self::assertCount(1, Token::all());

        $this->assertFalse($user->is_email_confirmed);
        $this->assertFalse($user->is_admin_confirmed);
        $this->assertFalse($user->is_confirmed);
        $this->assertNull($user->refLink);

        $this->activateUser($user);
        self::assertEmpty(Token::all());
        $this->assertTrue($user->is_email_confirmed);
        $this->assertTrue($user->is_admin_confirmed);
        $this->assertTrue($user->is_confirmed);
        $this->assertNull($user->refLink);
        Mail::assertNotQueued(UserConfirmed::class);
    }

    public function testManualActivationManualConfirmationRegister(): void
    {
        Config::updateAdminSettings([Config::EMAIL_VERIFICATION_REQUIRED => '1']);
        Config::updateAdminSettings([Config::AUTO_CONFIRMATION_ENABLED => '0']);

        $user = $this->registerUser();
        $this->assertFalse($user->is_email_confirmed);
        $this->assertFalse($user->is_admin_confirmed);
        $this->assertFalse($user->is_confirmed);

        $this->activateUser($user);
        $this->assertTrue($user->is_email_confirmed);
        $this->assertFalse($user->is_admin_confirmed);
        $this->assertFalse($user->is_confirmed);

        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');
        $this->confirmUser($user);
        $this->assertTrue($user->is_email_confirmed);
        $this->assertTrue($user->is_admin_confirmed);
        $this->assertTrue($user->is_confirmed);
        Mail::assertQueued(UserConfirmed::class);
    }

    public function testAutoActivationAutoConfirmationRegister(): void
    {
        Config::updateAdminSettings([Config::EMAIL_VERIFICATION_REQUIRED => '0']);
        Config::updateAdminSettings([Config::AUTO_CONFIRMATION_ENABLED => '1']);

        $user = $this->registerUser();
        $this->assertTrue($user->is_email_confirmed);
        $this->assertTrue($user->is_admin_confirmed);
        $this->assertTrue($user->is_confirmed);
    }

    public function testAutoActivationManualConfirmationRegister(): void
    {
        Config::updateAdminSettings([Config::EMAIL_VERIFICATION_REQUIRED => '0']);
        Config::updateAdminSettings([Config::AUTO_CONFIRMATION_ENABLED => '0']);

        $user = $this->registerUser();
        $this->assertTrue($user->is_email_confirmed);
        $this->assertFalse($user->is_admin_confirmed);
        $this->assertFalse($user->is_confirmed);

        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');
        $this->confirmUser($user);
        $this->assertTrue($user->is_email_confirmed);
        $this->assertTrue($user->is_admin_confirmed);
        $this->assertTrue($user->is_confirmed);
        Mail::assertQueued(UserConfirmed::class);
    }

    public function testRestrictedRegister(): void
    {
        Config::updateAdminSettings([Config::REGISTRATION_MODE => RegistrationMode::RESTRICTED]);

        $user = $this->registerUser(null, Response::HTTP_FORBIDDEN);
        $this->assertNull($user);

        $user = $this->registerUser('dummy-token', Response::HTTP_FORBIDDEN);
        $this->assertNull($user);

        $refLink = factory(RefLink::class)->create(['single_use' => true]);
        $user = $this->registerUser($refLink->token);
        $this->assertNotNull($user);

        $user = $this->registerUser($refLink->token, Response::HTTP_FORBIDDEN);
        $this->assertNull($user);
    }

    public function testPrivateRegister(): void
    {
        Config::updateAdminSettings([Config::REGISTRATION_MODE => RegistrationMode::PRIVATE]);

        $user = $this->registerUser(null, Response::HTTP_FORBIDDEN);
        $this->assertNull($user);

        $refLink = factory(RefLink::class)->create();
        $user = $this->registerUser($refLink->token, Response::HTTP_FORBIDDEN);
        $this->assertNull($user);
    }

    public function testRegisterWithReferral(): void
    {
        $refLink = factory(RefLink::class)->create();
        $this->assertFalse($refLink->used);

        $user = $this->registerUser($refLink->token);
        $this->assertNotNull($user->refLink);
        $this->assertEquals($refLink->token, $user->refLink->token);
        $this->assertTrue($user->refLink->used);

        $this->activateUser($user);
        $this->assertNotNull($user->refLink);
        $this->assertEquals($refLink->token, $user->refLink->token);
        $this->assertTrue($user->refLink->used);
    }

    public function testRegisterWithInvalidReferral(): void
    {
        $user = $this->registerUser('dummy_token');
        $this->assertNull($user->refLink);
    }

    public function testEmailActivateWithBonus(): void
    {
        $refLink = factory(RefLink::class)->create(['bonus' => 100, 'refund' => 0.5]);
        $user = $this->registerUser($refLink->token);

        self::assertSame(
            [0, 0, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWalletBalance(),
            ]
        );

        $this->activateUser($user);

        self::assertSame(
            [300, 300, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWalletBalance(),
            ]
        );

        $entry = UserLedgerEntry::where('user_id', $user->id)
            ->where('type', UserLedgerEntry::TYPE_BONUS_INCOME)
            ->firstOrFail();

        $this->assertEquals(300, $entry->amount);
        $this->assertNotNull($entry->refLink);
        $this->assertEquals($refLink->id, $entry->refLink->id);
    }

    public function testEmailActivateNoBonus(): void
    {
        $refLink = factory(RefLink::class)->create(['bonus' => 0, 'refund' => 0.5]);
        $user = $this->registerUser($refLink->token);
        self::assertSame(
            [0, 0, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWalletBalance(),
            ]
        );

        $this->activateUser($user);
        self::assertSame(
            [0, 0, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWalletBalance(),
            ]
        );
    }

    public function testActiveManualConfirmationWithBonus(): void
    {
        Config::updateAdminSettings([Config::AUTO_CONFIRMATION_ENABLED => '0']);

        $refLink = factory(RefLink::class)->create(['bonus' => 100, 'refund' => 0.5]);
        $user = $this->registerUser($refLink->token);

        self::assertSame(
            [0, 0, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWalletBalance(),
            ]
        );

        $this->activateUser($user);

        self::assertSame(
            [0, 0, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWalletBalance(),
            ]
        );

        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');
        $this->confirmUser($user);

        self::assertSame(
            [300, 300, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWalletBalance(),
            ]
        );

        $entry = UserLedgerEntry::where('user_id', $user->id)
            ->where('type', UserLedgerEntry::TYPE_BONUS_INCOME)
            ->firstOrFail();

        $this->assertEquals(300, $entry->amount);
        $this->assertNotNull($entry->refLink);
        $this->assertEquals($refLink->id, $entry->refLink->id);
    }

    public function testInactiveManualConfirmationWithBonus(): void
    {
        Config::updateAdminSettings([Config::AUTO_CONFIRMATION_ENABLED => '0']);

        $refLink = factory(RefLink::class)->create(['bonus' => 100, 'refund' => 0.5]);
        $user = $this->registerUser($refLink->token);

        self::assertSame(
            [0, 0, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWalletBalance(),
            ]
        );

        $this->actingAs(factory(User::class)->create(['is_admin' => 1]), 'api');
        $this->confirmUser($user);

        self::assertSame(
            [0, 0, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWalletBalance(),
            ]
        );

        $this->activateUser($user);

        self::assertSame(
            [300, 300, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWalletBalance(),
            ]
        );

        $entry = UserLedgerEntry::where('user_id', $user->id)
            ->where('type', UserLedgerEntry::TYPE_BONUS_INCOME)
            ->firstOrFail();

        $this->assertEquals(300, $entry->amount);
        $this->assertNotNull($entry->refLink);
        $this->assertEquals($refLink->id, $entry->refLink->id);
    }

    public function testCheck(): void
    {
        $this->app->bind(
            ExchangeRateRepository::class,
            function () {
                return new DummyExchangeRateRepository();
            }
        );

        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->getJson(self::CHECK_URI);

        $response->assertStatus(Response::HTTP_OK)->assertJsonStructure(self::STRUCTURE_CHECK);
    }

    public function testCheckWithoutExchangeRate(): void
    {
        $repository = $this->createMock(ExchangeRateRepository::class);
        $repository->expects($this->once())->method('fetchExchangeRate')->willThrowException(
            new ExchangeRateNotAvailableException()
        );

        $this->app->bind(
            ExchangeRateRepository::class,
            function () use ($repository) {
                return $repository;
            }
        );

        $this->actingAs(factory(User::class)->create(), 'api');

        $response = $this->getJson(self::CHECK_URI);

        $structure = self::STRUCTURE_CHECK;
        unset($structure['exchangeRate']);

        $response->assertStatus(Response::HTTP_OK)->assertJsonStructure($structure);
    }

    public function testWalletLoginInit(): void
    {
        $response = $this->get(self::WALLET_LOGIN_INIT_URI);
        $response->assertStatus(Response::HTTP_OK)->assertJsonStructure([
            'message',
            'token',
            'gateways' => ['bsc']
        ]);
    }

    public function testWalletLoginAds(): void
    {
        $user = $this->walletRegisterUser();
        $this->assertAuthenticatedAs($user);
    }

    public function testWalletLoginWithReferral(): void
    {
        $refLink = factory(RefLink::class)->create();
        $this->assertFalse($refLink->used);

        $user = $this->walletRegisterUser($refLink->token);
        $this->assertNotNull($user->refLink);
        $this->assertEquals($refLink->token, $user->refLink->token);
        $this->assertTrue($user->refLink->used);
    }

    public function testWalletLoginBsc(): void
    {
        $user = factory(User::class)->create([
            'wallet_address' => WalletAddress::fromString('bsc:0x79e51bA0407bEc3f1246797462EaF46850294301')
        ]);
        $message = '123abc';
        $token = Token::generate(Token::WALLET_LOGIN, null, [
            'request' => [],
            'message' => $message,
        ])->uuid;

        //message:123abc
        $sign = '0xe649d27a045e5a9397a9a7572d93471e58f6ab8d024063b2ea5b6bcb4f65b5eb4aecf499197f71af91f57cd712799d2a559e3a3a40243db2c4e947aeb0a2c8181b';
        $response = $this->post(self::WALLET_LOGIN_URI, [
            'token' => $token,
            'network' => 'bsc',
            'address' => '0x79e51bA0407bEc3f1246797462EaF46850294301',
            'signature' => $sign
        ]);
        $response->assertStatus(Response::HTTP_OK);
        $this->assertAuthenticatedAs($user);
    }

    public function testNonExistedWalletLoginUser(): void
    {
        factory(User::class)->create([
            'wallet_address' => WalletAddress::fromString('ads:0001-00000002-BB2D')
        ]);
        $message = '123abc';
        $token = Token::generate(Token::WALLET_LOGIN, null, [
            'request' => [],
            'message' => $message,
        ])->uuid;

        //SK: CA978112CA1BBDCAFAC231B39A23DC4DA786EFF8147C4E72B9807785AFEE48BB
        //PK: EAE1C8793B5597C4B3F490E76AC31172C439690F8EE14142BB851A61F9A49F0E
        //message:123abc
        $sign = '0x72d877601db72b6d843f11d634447bbdd836de7adbd5b2dfc4fa718ea68e7b18d65547b1265fec0c121ac76dfb086806da393d244dec76d72f49895f48aa5a01';
        $response = $this->post(self::WALLET_LOGIN_URI, [
            'token' => $token,
            'network' => 'ads',
            'address' => '0001-00000001-8B4E',
            'signature' => $sign
        ]);
        $response->assertStatus(Response::HTTP_OK);

        $user = User::fetchByWalletAddress(new WalletAddress(WalletAddress::NETWORK_ADS, '0001-00000001-8B4E'));
        $this->assertNotNull($user);
        $this->assertAuthenticatedAs($user);

        $this->assertFalse($user->is_email_confirmed);
        $this->assertTrue($user->is_admin_confirmed);
        $this->assertTrue($user->is_confirmed);
    }

    public function testNonExistedWalletLoginUserWithRestrictedMode(): void
    {
        Config::updateAdminSettings([Config::REGISTRATION_MODE => RegistrationMode::RESTRICTED]);

        factory(User::class)->create([
            'wallet_address' => WalletAddress::fromString('ads:0001-00000002-BB2D')
        ]);
        $message = '123abc';
        $token = Token::generate(Token::WALLET_LOGIN, null, [
            'request' => [],
            'message' => $message,
        ])->uuid;

        //SK: CA978112CA1BBDCAFAC231B39A23DC4DA786EFF8147C4E72B9807785AFEE48BB
        //PK: EAE1C8793B5597C4B3F490E76AC31172C439690F8EE14142BB851A61F9A49F0E
        //message:123abc
        $sign = '0x72d877601db72b6d843f11d634447bbdd836de7adbd5b2dfc4fa718ea68e7b18d65547b1265fec0c121ac76dfb086806da393d244dec76d72f49895f48aa5a01';
        $response = $this->post(self::WALLET_LOGIN_URI, [
            'token' => $token,
            'network' => 'ads',
            'address' => '0001-00000001-8B4E',
            'signature' => $sign
        ]);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testNonExistedWalletLoginUserWithPrivateMode(): void
    {
        Config::updateAdminSettings([Config::REGISTRATION_MODE => RegistrationMode::PRIVATE]);

        factory(User::class)->create([
            'wallet_address' => WalletAddress::fromString('ads:0001-00000002-BB2D')
        ]);
        $message = '123abc';
        $token = Token::generate(Token::WALLET_LOGIN, null, [
            'request' => [],
            'message' => $message,
        ])->uuid;

        //SK: CA978112CA1BBDCAFAC231B39A23DC4DA786EFF8147C4E72B9807785AFEE48BB
        //PK: EAE1C8793B5597C4B3F490E76AC31172C439690F8EE14142BB851A61F9A49F0E
        //message:123abc
        $sign = '0x72d877601db72b6d843f11d634447bbdd836de7adbd5b2dfc4fa718ea68e7b18d65547b1265fec0c121ac76dfb086806da393d244dec76d72f49895f48aa5a01';
        $response = $this->post(self::WALLET_LOGIN_URI, [
            'token' => $token,
            'network' => 'ads',
            'address' => '0001-00000001-8B4E',
            'signature' => $sign
        ]);
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testInvalidWalletLoginSignature(): void
    {
        factory(User::class)->create([
            'wallet_address' => WalletAddress::fromString('ads:0001-00000001-8B4E')
        ]);
        $message = '123abc';
        $token = Token::generate(Token::WALLET_LOGIN, null, [
            'request' => [],
            'message' => $message,
        ])->uuid;

        $response = $this->post(self::WALLET_LOGIN_URI, [
            'token' => $token,
            'network' => 'ads',
            'address' => '0001-00000001-8B4E',
            'signature' => '0x1231231231'
        ]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUnsupportedWalletLoginNetwork(): void
    {
        factory(User::class)->create([
            'wallet_address' => WalletAddress::fromString('btc:3ALP7JRzHAyrhX5LLPSxU1A9duDiGbnaKg')
        ]);
        $message = '123abc';
        $token = Token::generate(Token::WALLET_LOGIN, null, [
            'request' => [],
            'message' => $message,
        ])->uuid;

        $response = $this->post(self::WALLET_LOGIN_URI, [
            'token' => $token,
            'network' => 'btc',
            'address' => '3ALP7JRzHAyrhX5LLPSxU1A9duDiGbnaKg',
            'signature' => '0x1231231231'
        ]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testInvalidWalletLoginToken(): void
    {
        factory(User::class)->create([
            'wallet_address' => WalletAddress::fromString('ads:0001-00000001-8B4E')
        ]);
        $sign = '0x72d877601db72b6d843f11d634447bbdd836de7adbd5b2dfc4fa718ea68e7b18d65547b1265fec0c121ac76dfb086806da393d244dec76d72f49895f48aa5a01';
        $response = $this->post(self::WALLET_LOGIN_URI, [
            'token' => 'foo_token',
            'network' => 'ads',
            'address' => '0001-00000001-8B4E',
            'signature' => $sign
        ]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testNonExistedWalletLoginToken(): void
    {
        factory(User::class)->create([
            'wallet_address' => WalletAddress::fromString('ads:0001-00000001-8B4E')
        ]);
        $sign = '0x72d877601db72b6d843f11d634447bbdd836de7adbd5b2dfc4fa718ea68e7b18d65547b1265fec0c121ac76dfb086806da393d244dec76d72f49895f48aa5a01';
        $response = $this->post(self::WALLET_LOGIN_URI, [
            'token' => '1231231231',
            'network' => 'ads',
            'address' => '0001-00000001-8B4E',
            'signature' => $sign
        ]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testExpiredWalletLoginToken(): void
    {
        factory(User::class)->create([
            'wallet_address' => WalletAddress::fromString('ads:0001-00000001-8B4E')
        ]);
        $message = '123abc';
        $token = Token::generate(Token::WALLET_LOGIN, null, [
            'request' => [],
            'message' => $message,
        ]);
        $token->valid_until = '2020-01-01 12:00:00';
        $token->saveOrFail();

        $sign = '0x72d877601db72b6d843f11d634447bbdd836de7adbd5b2dfc4fa718ea68e7b18d65547b1265fec0c121ac76dfb086806da393d244dec76d72f49895f48aa5a01';
        $response = $this->post(self::WALLET_LOGIN_URI, [
            'token' => $token->uuid,
            'network' => 'ads',
            'address' => '0001-00000001-8B4E',
            'signature' => $sign
        ]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSetPassword(): void
    {
        $user = $this->walletRegisterUser();
        $this->actingAs($user, 'api');

        $response = $this->patch(self::SELF_URI, [
            'user' => [
                'password_new' => 'qwerty123',
            ]
        ]);
        $response->assertStatus(Response::HTTP_OK);
    }

    public function testSetInvalidPassword(): void
    {
        $user = $this->walletRegisterUser();
        $this->actingAs($user, 'api');

        $response = $this->patch(self::SELF_URI, [
            'user' => [
                'password_new' => '123',
            ]
        ]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testChangePassword(): void
    {
        $user = $this->registerUser();
        $this->actingAs($user, 'api');

        $response = $this->patch(self::SELF_URI, [
            'user' => [
                'password_old' => '87654321',
                'password_new' => 'qwerty123',
            ]
        ]);
        $response->assertStatus(Response::HTTP_OK);
    }

    public function testChangeInvalidOldPassword(): void
    {
        $user = $this->registerUser();
        $this->actingAs($user, 'api');

        $response = $this->patch(self::SELF_URI, [
            'user' => [
                'password_old' => 'foopass123',
                'password_new' => 'qwerty123',
            ]
        ]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testChangeInvalidNewPassword(): void
    {
        $user = $this->registerUser();
        $this->actingAs($user, 'api');

        $response = $this->patch(self::SELF_URI, [
            'user' => [
                'password_old' => '87654321',
                'password_new' => 'foo',
            ]
        ]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSetEmail(): void
    {
        Config::updateAdminSettings([Config::EMAIL_VERIFICATION_REQUIRED => '0']);

        $user = $this->walletRegisterUser();
        $this->actingAs($user, 'api');

        $response = $this->post(self::EMAIL_URI, [
            'email' => $this->faker->unique()->email,
            'uri_step1' => '/auth/email-activation/',
            'uri_step2' => '/auth/email-activation/'
        ]);
        $response->assertStatus(Response::HTTP_NO_CONTENT);
    }

    public function testSetEmailStep1(): void
    {
        Config::updateAdminSettings([Config::EMAIL_VERIFICATION_REQUIRED => '1']);

        $user = $this->walletRegisterUser();
        $this->actingAs($user, 'api');

        $response = $this->post(self::EMAIL_URI, [
            'email' => $this->faker->unique()->email,
            'uri_step1' => '/auth/email-activation/',
            'uri_step2' => '/auth/email-activation/'
        ]);
        $response->assertStatus(Response::HTTP_NO_CONTENT);
        Mail::assertQueued(UserEmailActivate::class);

    }

    public function testSetInvalidEmail(): void
    {
        $user = $this->walletRegisterUser();
        $this->actingAs($user, 'api');

        $response = $this->post(self::EMAIL_URI, [
            'email' => 'foo',
            'uri_step1' => '/auth/email-activation/',
            'uri_step2' => '/auth/email-activation/'
        ]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
    
    public function testChangeEmailStep1(): void
    {
        Config::updateAdminSettings([Config::EMAIL_VERIFICATION_REQUIRED => '1']);

        $user = $this->registerUser();
        $this->actingAs($user, 'api');

        $response = $this->post(self::EMAIL_URI, [
            'email' => $this->faker->unique()->email,
            'uri_step1' => '/auth/email-activation/',
            'uri_step2' => '/auth/email-activation/'
        ]);
        $response->assertStatus(Response::HTTP_NO_CONTENT);
        Mail::assertQueued(UserEmailChangeConfirm1Old::class);
    }

    public function testChangeInvalidEmail(): void
    {
        $user = $this->registerUser();
        $this->actingAs($user, 'api');

        $response = $this->post(self::EMAIL_URI, [
            'email' => 'foo',
            'uri_step1' => '/auth/email-activation/',
            'uri_step2' => '/auth/email-activation/'
        ]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function registerUser(?string $referralToken = null, int $status = Response::HTTP_CREATED): ?User
    {
        $email = $this->faker->unique()->email;
        $response = $this->postJson(
            '/auth/register',
            [
                'user' => [
                    'email' => $email,
                    'password' => '87654321',
                    'referral_token' => $referralToken,
                ],
                'uri' => '/auth/email-activation/',
            ]
        );
        $response->assertStatus($status);

        return User::where('email', $email)->first();
    }

    private function walletRegisterUser(?string $referralToken = null, int $status = Response::HTTP_OK): ?User
    {
        $message = '123abc';
        $token = Token::generate(Token::WALLET_LOGIN, null, [
            'request' => [],
            'message' => $message,
        ])->uuid;

        //SK: CA978112CA1BBDCAFAC231B39A23DC4DA786EFF8147C4E72B9807785AFEE48BB
        //PK: EAE1C8793B5597C4B3F490E76AC31172C439690F8EE14142BB851A61F9A49F0E
        //message:123abc
        $sign = '0x72d877601db72b6d843f11d634447bbdd836de7adbd5b2dfc4fa718ea68e7b18d65547b1265fec0c121ac76dfb086806da393d244dec76d72f49895f48aa5a01';
        $response = $this->post(self::WALLET_LOGIN_URI, [
            'token' => $token,
            'network' => 'ads',
            'address' => '0001-00000001-8B4E',
            'signature' => $sign,
            'referral_token' => $referralToken
        ]);
        $response->assertStatus($status);

        return User::fetchByWalletAddress(new WalletAddress(WalletAddress::NETWORK_ADS, '0001-00000001-8B4E'));
    }

    private function activateUser(User $user): void
    {
        $activationToken = Token::where('user_id', $user->id)->where('tag', 'email-activate')->firstOrFail();

        $response = $this->postJson(
            '/auth/email/activate',
            [
                'user' => [
                    'emailConfirmToken' => $activationToken->uuid,
                ],
            ]
        );
        $response->assertStatus(Response::HTTP_OK);
        $user->refresh();
    }

    private function confirmUser(User $user): void
    {
        $response = $this->postJson('/admin/users/' . $user->id . '/confirm');
        $response->assertStatus(Response::HTTP_OK);
        $user->refresh();
    }
}
