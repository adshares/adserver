<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer.  If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Http\Controllers\App;

use Adshares\Adserver\Mail\AuthRecovery;
use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AuthController extends AppController
{
    protected $password_recovery_resend_limit = 2 * 60; //2 minutes
    protected $password_recovery_token_time = 120 * 60; // 2 hours

    /**
     * Log the user out of the application.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function check(Request $request)
    {
        return self::json(Auth::user()->toArrayCamelize(), 200);
    }

    /**
     * Log the user out of the application.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        if (Auth::guard()->attempt(
            $request->only('email', 'password'),
            $request->filled('remember')
        )) {
            Auth::user()->generateApiKey();

            return self::json(Auth::user()->load('AdserverWallet')->toArrayCamelize(), 200);
        }

        return self::json([], 400);
    }

    /**
     * Log the user out of the application.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        Auth::user()->clearApiKey();

        return self::json([], 204);
    }

    /**
     * Start password recovery process - generate and send email.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function recovery(Request $request)
    {
        Validator::make($request->all(), ['email' => 'required|email', 'uri' => 'required'])->validate();
        $user = User::where('email', $request->input('email'))->first();
        if (empty($user)) {
            return self::json([], 204);
        }
        DB::beginTransaction();
        if (!Token::canGenerate($user->id, 'password-recovery', $this->password_recovery_resend_limit)) {
            return self::json([], 204);
        }
        Mail::to($user)->queue(new AuthRecovery(
            Token::generate('password-recovery', $this->password_recovery_token_time, $user->id),
            $request->input('uri')
        ));
        DB::commit();

        return self::json([], 204);
    }

    /**
     * Tests and extends user password recovery token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function recoveryTokenExtend($token)
    {
        if (Token::extend($token, $this->password_recovery_token_time, null, 'password-recovery')) {
            return self::json([], 204);
        }

        return self::json([], 422, ['message' => 'Password recovery token is invalid']);
    }
}
