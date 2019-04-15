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

use Adshares\Adserver\Mail\UserEmailActivate;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

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

    public function testEmailActivate(): void
    {
        Config::upsertInt(Config::BONUS_NEW_USER_ENABLED, 1);
        Config::upsertInt(Config::BONUS_NEW_USER_AMOUNT, 1000);

        $activationToken = $this->testRegister();

        $user = User::find($activationToken->user_id)->first();

        self::assertSame([0, 0, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWalletBalance(),
            ]);

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

        self::assertSame([1000, 1000, 0],
            [
                $user->getBalance(),
                $user->getBonusBalance(),
                $user->getWalletBalance(),
            ]);
    }
}
