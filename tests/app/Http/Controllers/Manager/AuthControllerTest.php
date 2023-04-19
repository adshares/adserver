<?php
/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Http\Controllers\Manager;

use Adshares\Adserver\Mail\UserConfirmed;
use Adshares\Adserver\Mail\UserEmailActivate;
use Adshares\Adserver\Mail\UserEmailChangeConfirm1Old;
use Adshares\Adserver\Mail\UserPasswordChange;
use Adshares\Adserver\Mail\UserPasswordChangeConfirm;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\RefLink;
use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Model\Currency;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Application\Service\ExchangeRateRepository;
use Adshares\Common\Domain\ValueObject\WalletAddress;
use Adshares\Config\RegistrationMode;
use DateTime;
use DateTimeImmutable;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

class AuthControllerTest extends TestCase
{
    private const CHECK_URI = '/auth/check';
    private const SELF_URI = '/auth/self';
    private const PASSWORD_CONFIRM = '/auth/password/confirm';
    private const PASSWORD_URI = '/auth/password';
    private const EMAIL_ACTIVATE_URI = '/auth/email/activate';
    private const EMAIL_URI = '/auth/email';
    private const EMAIL_ACTIVATE_RESEND_URI = '/auth/email/activate/resend';
    private const LOG_IN_URI = '/auth/login';
    private const LOG_OUT_URI = '/auth/logout';
    private const REGISTER_USER = '/auth/register';
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
            'withdrawableBalance',
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

    protected function setUp(): void
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

    public function testAutoActivationManualConfirmationRequired(): void
    {
        Config::updateAdminSettings([Config::EMAIL_VERIFICATION_REQUIRED => '0']);
        Config::updateAdminSettings([Config::AUTO_CONFIRMATION_ENABLED => '0']);

        $user = $this->registerUser();
        $this->assertTrue($user->is_email_confirmed);
        $this->assertFalse($user->is_admin_confirmed);
        $this->assertFalse($user->is_confirmed);
    }

    public function testRestrictedRegister(): void
    {
        Config::updateAdminSettings([Config::REGISTRATION_MODE => RegistrationMode::RESTRICTED]);

        $user = $this->registerUser(null, Response::HTTP_FORBIDDEN);
        $this->assertNull($user);

        $user = $this->registerUser('dummy-token', Response::HTTP_FORBIDDEN);
        $this->assertNull($user);

        /** @var RefLink $refLink */
        $refLink = RefLink::factory()->create(['single_use' => true]);
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

        /** @var RefLink $refLink */
        $refLink = RefLink::factory()->create();
        $user = $this->registerUser($refLink->token, Response::HTTP_FORBIDDEN);
        $this->assertNull($user);
    }

    public function testRegisterWithReferral(): void
    {
        /** @var RefLink $refLink */
        $refLink = RefLink::factory()->create();
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

    /**
     * @dataProvider emailActivateWithBonusProvider
     */
    public function testEmailActivateWithBonus(Currency $currency, int $definedBonus, int $expectedBonusIncome): void
    {
        Config::updateAdminSettings([Config::CURRENCY => $currency->value]);
        /** @var RefLink $refLink */
        $refLink = RefLink::factory()->create(['bonus' => $definedBonus, 'refund' => 0.5]);
        $user = $this->registerUser($refLink->token);

        self::assertSame(
            [0, 0, 0, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWithdrawableBalance(),
                $user->getWalletBalance(),
            ]
        );

        $this->activateUser($user);

        self::assertSame(
            [$expectedBonusIncome, $expectedBonusIncome, 0, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWithdrawableBalance(),
                $user->getWalletBalance(),
            ]
        );

        $entry = UserLedgerEntry::where('user_id', $user->id)
            ->where('type', UserLedgerEntry::TYPE_BONUS_INCOME)
            ->firstOrFail();

        $this->assertEquals($expectedBonusIncome, $entry->amount);
        $this->assertNotNull($entry->refLink);
        $this->assertEquals($refLink->id, $entry->refLink->id);
    }

    public function emailActivateWithBonusProvider(): array
    {
        return [
            'ADS' => [
                Currency::ADS,
                1_000_000_000_000,// defined bonus in currency
                3_000_300_030_003,// accounted bonus is ADS, = x / 0.3333
            ],
            'USD' => [
                Currency::USD,
                1_000_000_000_000,// defined bonus in currency
                1_000_000_000_000,// accounted bonus in currency
            ],
        ];
    }

    public function testEmailActivateWhileExchangeRateUnavailable(): void
    {
        $this->app->bind(ExchangeRateRepository::class, function () {
            $mock = self::createMock(ExchangeRateRepository::class);
            $mock->method('fetchExchangeRate')->willThrowException(new ExchangeRateNotAvailableException());
            return $mock;
        });

        /** @var RefLink $refLink */
        $refLink = RefLink::factory()->create(['bonus' => 1_000_000_000_000, 'refund' => 0.5]);
        $user = $this->registerUser($refLink->token);

        self::assertSame(
            [0, 0, 0, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWithdrawableBalance(),
                $user->getWalletBalance(),
            ]
        );

        $this->activateUser($user);

        self::assertSame(
            [0, 0, 0, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWithdrawableBalance(),
                $user->getWalletBalance(),
            ]
        );

        self::assertDatabaseMissing(UserLedgerEntry::class, ['type' => UserLedgerEntry::TYPE_BONUS_INCOME]);
    }

    public function testEmailActivateNoBonus(): void
    {
        /** @var RefLink $refLink */
        $refLink = RefLink::factory()->create(['bonus' => 0, 'refund' => 0.5]);
        $user = $this->registerUser($refLink->token);
        self::assertSame(
            [0, 0, 0, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWithdrawableBalance(),
                $user->getWalletBalance(),
            ]
        );

        $this->activateUser($user);
        self::assertSame(
            [0, 0, 0, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWithdrawableBalance(),
                $user->getWalletBalance(),
            ]
        );
    }

    public function testActivateManualConfirmationRequired(): void
    {
        Config::updateAdminSettings([Config::AUTO_CONFIRMATION_ENABLED => '0']);

        /** @var RefLink $refLink */
        $refLink = RefLink::factory()->create(['bonus' => 100, 'refund' => 0.5]);
        $user = $this->registerUser($refLink->token);

        self::assertSame(
            [0, 0, 0, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWithdrawableBalance(),
                $user->getWalletBalance(),
            ]
        );

        $this->activateUser($user);

        self::assertSame(
            [0, 0, 0, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWithdrawableBalance(),
                $user->getWalletBalance(),
            ]
        );
    }

    public function testAwardBonusOnActivationWhileManualConfirmation(): void
    {
        Config::updateAdminSettings([Config::AUTO_CONFIRMATION_ENABLED => '0']);

        /** @var RefLink $refLink */
        $refLink = RefLink::factory()->create(['bonus' => 100, 'refund' => 0.5]);
        $user = User::factory()->create(
            [
                'admin_confirmed_at' => new DateTimeImmutable(),
                'email' => $this->faker->email,
                'ref_link_id' => $refLink->id,
            ]
        );
        Token::generate(Token::EMAIL_ACTIVATE, $user);

        self::assertSame(
            [0, 0, 0, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWithdrawableBalance(),
                $user->getWalletBalance(),
            ]
        );

        $this->activateUser($user);

        self::assertSame(
            [300, 300, 0, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWithdrawableBalance(),
                $user->getWalletBalance(),
            ]
        );
    }

    public function testConfirmNonExistingUser(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'api');

        $response = $this->postJson('/admin/users/999/confirm');
        $response->assertStatus(Response::HTTP_NOT_FOUND);
    }

    /**
     * @dataProvider currencyProvider
     */
    public function testCheck(Currency $currency, float $expectedRate): void
    {
        Config::updateAdminSettings([Config::CURRENCY => $currency->value]);
        $this->login();

        $response = $this->getJson(self::CHECK_URI);

        $response->assertStatus(Response::HTTP_OK)
            ->assertJsonStructure(self::STRUCTURE_CHECK);
        $rate = json_decode($response->getContent())->exchangeRate->value;
        self::assertEquals($expectedRate, $rate);
    }

    public function currencyProvider(): array
    {
        return [
            'ADS' => [Currency::ADS, 0.3333],
            'USD' => [Currency::USD, 1.0],
        ];
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
        $this->login();

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
        /** @var RefLink $refLink */
        $refLink = RefLink::factory()->create();
        $this->assertFalse($refLink->used);

        $user = $this->walletRegisterUser($refLink->token);
        $this->assertNotNull($user->refLink);
        $this->assertEquals($refLink->token, $user->refLink->token);
        $this->assertTrue($user->refLink->used);
    }

    public function testWalletLoginBsc(): void
    {
        $user = User::factory()->create([
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
        User::factory()->create([
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

        User::factory()->create([
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

        User::factory()->create([
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
        User::factory()->create([
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

    public function testInvalidWallet(): void
    {
        $response = $this->post(self::WALLET_LOGIN_URI, [
            'network' => 'invalid',
            'address' => 'invalid',
        ]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUnsupportedWalletLoginNetwork(): void
    {
        User::factory()->create([
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
        User::factory()->create([
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
        User::factory()->create([
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
        User::factory()->create([
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

    public function testLogInMissingEmail(): void
    {
        $response = $this->post(self::LOG_IN_URI, [
            'password' => '87654321',
        ]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testLogInAndLogOut(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['invalid_login_attempts' => 2, 'password' => '87654321']);

        $this->post(self::LOG_IN_URI, ['email' => $user->email, 'password' => '87654321'])
            ->assertStatus(Response::HTTP_OK);
        $user->refresh();
        $apiToken = $user->api_token;
        self::assertNotNull($apiToken, 'Token is null');
        self::assertEquals(0, $user->invalid_login_attempts);

        $this->get(self::LOG_OUT_URI, ['Authorization' => 'Bearer ' . $apiToken])
            ->assertStatus(Response::HTTP_NO_CONTENT);
        self::assertNull(User::fetchById($user->id)->api_token, 'Token is not null');
    }

    public function testLogInBannedUser(): void
    {
        /** @var User $user */
        $user = User::factory()
            ->create(['password' => '87654321', 'is_banned' => true, 'ban_reason' => 'suspicious activity']);

        $response = $this->post(self::LOG_IN_URI, ['email' => $user->email, 'password' => '87654321']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $response->assertJsonPath('reason', 'suspicious activity');
    }

    public function testLogInLockedUser(): void
    {
        /** @var User $user */
        $user = User::factory()
            ->create(['invalid_login_attempts' => 5, 'password' => '87654321']);

        $response = $this->post(self::LOG_IN_URI, ['email' => $user->email, 'password' => '87654321']);

        $response->assertStatus(Response::HTTP_FORBIDDEN);
        $response->assertJsonPath('reason', 'Account locked. Reset password');
    }

    public function testLogInInvalidPassword(): void
    {
        /** @var User $user */
        $user = User::factory()
            ->create(['password' => '87654321']);

        $response = $this->post(self::LOG_IN_URI, ['email' => $user->email, 'password' => '876543210']);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertDatabaseHas(User::class, [
            'email' => $user->email,
            'invalid_login_attempts' => 1,
        ]);
    }

    public function testSetPassword(): void
    {
        $user = $this->walletRegisterUser();
        $user->email = $this->faker->email();
        $user->email_confirmed_at = new DateTime();
        $user->save();
        $this->actingAs($user, 'api');

        $response = $this->patch(self::SELF_URI, [
            'user' => [
                'password_new' => 'qwerty123',
            ],
            'uri' => '/confirm',
        ]);
        $response->assertStatus(Response::HTTP_OK);
        Mail::assertQueued(UserPasswordChangeConfirm::class);
    }

    public function testSetPasswordWhileUserHasNoEmailSet(): void
    {
        $user = $this->walletRegisterUser();
        $this->actingAs($user, 'api');

        $response = $this->patch(self::SELF_URI, [
            'user' => [
                'password_new' => 'qwerty123',
            ],
            'uri' => '/confirm',
        ]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSetPasswordConfirm(): void
    {
        $user = $this->walletRegisterUser();
        $user->email = $this->faker->email();
        $user->email_confirmed_at = new DateTime();
        $user->save();
        $this->actingAs($user, 'api');
        $token = Token::generate(Token::PASSWORD_CHANGE, $user, ['password' => 'qwerty123']);

        $response = $this->post(self::buildConfirmPasswordUri($token->uuid));
        $response->assertStatus(Response::HTTP_OK);

        $user->refresh();
        self::assertEquals('qwerty123', $user->password);
    }

    public function testSetPasswordConfirmInvalidToken(): void
    {
        $user = $this->walletRegisterUser();
        $user->email = $this->faker->email();
        $user->email_confirmed_at = new DateTime();
        $user->save();
        $this->actingAs($user, 'api');

        $response = $this->post(self::buildConfirmPasswordUri('foo'));
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSetPasswordConfirmInvalidEmail(): void
    {
        $user = $this->walletRegisterUser();
        $this->actingAs($user, 'api');
        $token = Token::generate(Token::PASSWORD_CHANGE, $user, ['password' => 'qwerty123']);

        $response = $this->post(self::buildConfirmPasswordUri($token->uuid));
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private static function buildConfirmPasswordUri(string $token): string
    {
        return self::PASSWORD_CONFIRM . '/' . $token;
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
        /** @var User $user */
        $user = User::factory()->create([
            'api_token' => '1234',
            'invalid_login_attempts' => 10,
            'password' => '87654321',
        ]);
        $this->login($user);

        $response = $this->patch(
            self::SELF_URI,
            [
                'user' => [
                    'password_old' => '87654321',
                    'password_new' => 'qwerty123',
                ]
            ]
        );

        $response->assertStatus(Response::HTTP_OK);
        $user->refresh();
        self::assertNull($user->api_token, 'Token is not null');
        self::assertEquals(0, $user->invalid_login_attempts);
        Mail::assertQueued(UserPasswordChange::class);
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

    public function testChangePasswordNoPassword(): void
    {
        $this->actingAs(User::factory()->create(), 'api');

        $response = $this->patch(self::SELF_URI, [
            'user' => [
                'email' => $this->faker->email(),
            ]
        ]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testChangePasswordNoUser(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $token = Token::generate(Token::PASSWORD_RECOVERY, $user);

        $response = $this->patch(self::PASSWORD_URI, [
            'user' => [
                'password_new' => '1234567890',
                'token' => $token->uuid,
            ]
        ]);
        $response->assertStatus(Response::HTTP_OK);
        self::assertNotEquals($user->password, User::fetchById($user->id)->password);
    }

    public function testChangePasswordNoToken(): void
    {
        $response = $this->patch(self::PASSWORD_URI, [
            'user' => [
                'password_new' => '1234567890',
                'token' => '0123456789ABCDEF0123456789ABCDEF',
            ]
        ]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testChangePasswordUnauthorized(): void
    {
        $response = $this->patch(self::PASSWORD_URI, [
            'user' => [
                'password_new' => 'qwerty123',
            ]
        ]);
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
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

    public function testChangeEmail(): void
    {
        Config::updateAdminSettings([Config::EMAIL_VERIFICATION_REQUIRED => '0']);

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

    public function testEmailActivationNoToken(): void
    {
        $response = $this->postJson(
            self::EMAIL_ACTIVATE_URI,
            [
                'user' => [
                    'emailConfirmToken' => '00',
                ],
            ]
        );
        $response->assertStatus(Response::HTTP_FORBIDDEN);
    }

    public function testRegisterDeletedUser(): void
    {
        /** @var User $user */
        $user = User::factory()->create(['deleted_at' => new DateTime()]);

        $response = $this->postJson(
            self::REGISTER_USER,
            [
                'user' => [
                    'email' => $user->email,
                    'password' => '87654321',
                ],
                'uri' => '/auth/email-activation/',
            ]
        );

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testEmailActivateResend(): void
    {
        $user = $this->walletRegisterUser();
        $this->actingAs($user, 'api');

        $response = $this->post(self::EMAIL_ACTIVATE_RESEND_URI, [
            'uri' => '/auth/email-activation/',
        ]);
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function registerUser(?string $referralToken = null, int $status = Response::HTTP_CREATED): ?User
    {
        $email = $this->faker->unique()->email;
        $response = $this->postJson(
            self::REGISTER_USER,
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
        $activationToken = Token::where('user_id', $user->id)->where('tag', Token::EMAIL_ACTIVATE)->firstOrFail();

        $response = $this->postJson(
            self::EMAIL_ACTIVATE_URI,
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

    /// Begin tests for foreign ecosystem
    public function testRegisterForeign(): void
    {

        // Non Exists
        /** @var User $user */
        $rnd = User::generateRandomETHWallet();

        $response = $this->postJson(
            '/auth/foreign/register',
            [
                'address' => $rnd,
            ]
        );
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'foreignId',
            'zones' => [ 0 => ["name", "width", "height", "uuid"]]
        ]);
        $sadId = $response->json()['foreignId'];

        $u = User::fetchByForeignWalletAddress($rnd);
        $this->assertEquals($sadId, $u->wallet_address);
        // Exists
        /** @var User $user */

        $response = $this->postJson(
            '/auth/foreign/register',
            [
                'address' => $rnd,
            ]
        );

        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'foreignId'
        ]);

        $this->assertEquals($sadId, $response->json()['foreignId']);
    }

    /// end tests for foreign ecosystem
}
