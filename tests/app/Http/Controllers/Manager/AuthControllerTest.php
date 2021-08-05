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

declare(strict_types=1);

namespace Adshares\Adserver\Tests\Http\Controllers\Manager;

use Adshares\Adserver\Mail\UserEmailActivate;
use Adshares\Adserver\Mail\UserConfirmed;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\RefLink;
use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserLedgerEntry;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Application\Service\ExchangeRateRepository;
use Adshares\Config\RegistrationMode;
use Adshares\Mock\Client\DummyExchangeRateRepository;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

class AuthControllerTest extends TestCase
{
    private const URI_CHECK = '/auth/check';

    private const STRUCTURE_CHECK = [
        'uuid',
        'email',
        'isAdvertiser',
        'isPublisher',
        'isAdmin',
        'adserverWallet' => [
            'totalFunds',
            'walletBalance',
            'bonusBalance',
            'totalFundsInCurrency',
            'totalFundsChange',
            'lastPaymentAt',
        ],
        'isEmailConfirmed',
        'isConfirmed',
        'exchangeRate' => [
            'validAt',
            'value',
            'currency',
        ],
    ];

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

    public function testManualConfirmationRegister(): void
    {
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

        $response = $this->getJson(self::URI_CHECK);

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

        $response = $this->getJson(self::URI_CHECK);

        $structure = self::STRUCTURE_CHECK;
        unset($structure['exchangeRate']);

        $response->assertStatus(Response::HTTP_OK)->assertJsonStructure($structure);
    }

    public function registerUser(?string $referralToken = null, int $status = Response::HTTP_CREATED): ?User
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

    public function activateUser(User $user): void
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

    public function confirmUser(User $user): void
    {
        $response = $this->postJson('/admin/users/' . $user->id . '/confirm');
        $response->assertStatus(Response::HTTP_OK);
        $user->refresh();
    }
}
