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

namespace Adshares\Adserver\Tests\Http\Controllers\Manager;

use Adshares\Adserver\Client\DummyExchangeRateRepository;
use Adshares\Adserver\Mail\UserEmailActivate;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Application\Service\ExchangeRateRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

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
        'exchangeRate' => [
            'validAt',
            'value',
            'currency',
        ],
    ];

    public function testRegister(): Token
    {
        Mail::fake();

        $response = $this->postJson(
            '/auth/register',
            [
                'user' => [
                    'email' => 'tester@test.xx',
                    'password' => '87654321',
                    'isAdvertiser' => true,
                    'isPublisher' => true,
                ],
                'uri' => '/auth/email-activation/',
            ]
        );

        $response->assertStatus(Response::HTTP_CREATED);

        Mail::assertQueued(UserEmailActivate::class);

        self::assertCount(1, Token::all());

        return Token::first();
    }

    public function testEmailActivateWithBonus(): void
    {
        Config::upsertInt(Config::BONUS_NEW_USER_ENABLED, 1);
        Config::upsertInt(Config::BONUS_NEW_USER_AMOUNT, 1000);

        $activationToken = $this->testRegister();

        $user = User::find($activationToken->user_id)->first();

        self::assertSame(
            [0, 0, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWalletBalance(),
            ]
        );

        $response = $this->postJson(
            '/auth/email/activate',
            [
                'user' => [
                    'emailConfirmToken' => $activationToken->uuid,
                ],
            ]
        );

        $response->assertStatus(Response::HTTP_OK);
        self::assertEmpty(Token::all());

        self::assertSame(
            [1000, 1000, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWalletBalance(),
            ]
        );
    }

    public function testEmailActivateNoBonus(): void
    {
        Config::upsertInt(Config::BONUS_NEW_USER_ENABLED, 0);

        $activationToken = $this->testRegister();

        $user = User::find($activationToken->user_id)->first();

        self::assertSame(
            [0, 0, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWalletBalance(),
            ]
        );

        $response = $this->postJson(
            '/auth/email/activate',
            [
                'user' => [
                    'emailConfirmToken' => $activationToken->uuid,
                ],
            ]
        );

        $response->assertStatus(Response::HTTP_OK);
        self::assertEmpty(Token::all());

        self::assertSame(
            [0, 0, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWalletBalance(),
            ]
        );
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
}
