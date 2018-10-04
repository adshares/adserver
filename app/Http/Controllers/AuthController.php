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

namespace Adshares\Adserver\Http\Controllers;

use Adshares\Adserver\Mail\AuthRecovery;
use Adshares\Adserver\Mail\UserEmailActivate;
use Adshares\Adserver\Mail\UserEmailChangeConfirm1Old;
use Adshares\Adserver\Mail\UserEmailChangeConfirm2New;
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
    protected $email_activation_token_time = 24 * 60 * 60; // 24 hours
    protected $email_activation_resend_limit = 15 * 60; // 15 minutes
    protected $email_change_token_time = 60 * 60; // 1 hour
    protected $email_new_change_resend_limit = 5 * 60; // 1 minute

    public function register(Request $request)
    {
        $this->validateRequestObject($request, 'user', User::$rules_add);
        Validator::make($request->all(), ['uri' => 'required'])->validate();

        DB::beginTransaction();
        $user = User::register($request->input('user'));
        Mail::to($user)->queue(
            new UserEmailActivate(
                Token::generate('email-activate', $this->email_activation_token_time, $user->id),
                $request->input('uri')
            )
        );
        DB::commit();

        return self::json($user->toArray(), 201);
    }

    public function emailActivate(Request $request)
    {
        Validator::make($request->all(), ['user.email_confirm_token' => 'required'])->validate();

        DB::beginTransaction();
        if (false === $token = Token::check($request->input('user.email_confirm_token'))) {
            return self::json([], 403);
        }
        $user = User::find($token['user_id']);
        if (empty($user)) {
            return self::json([], 403);
        }
        $user->email_confirmed_at = date('Y-m-d H:i:s');
        $user->save();
        DB::commit();

        return self::json($user->toArray(), 200);
    }

    public function emailActivateResend(Request $request)
    {
        Validator::make($request->all(), ['uri' => 'required'])->validate();
        $user = Auth::user();
        DB::beginTransaction();
        if (!Token::canGenerate($user->id, 'email-activate', $this->email_activation_resend_limit)) {
            return self::json(
                [],
                429,
                [
                    'message' => 'You can request to resend email activation every 15 minutes.'
                        . ' Please wait 15 minutes or less.',
                ]
            );
        }
        Mail::to($user)->queue(
            new UserEmailActivate(
                Token::generate('email-activate', $this->email_activation_token_time, $user->id),
                $request->input('uri')
            )
        );
        DB::commit();

        return self::json([], 204);
    }

    public function emailChangeStep1(Request $request)
    {
        Validator::make(
            $request->all(),
            ['email' => 'required|email', 'uri_step1' => 'required', 'uri_step2' => 'required']
        )->validate();
        if (User::withTrashed()->where('email', $request->input('email'))->count()) {
            return self::json([], 422, ['email' => 'This email already exists in our database']);
        }

        $user = Auth::user();
        DB::beginTransaction();
        if (!Token::canGenerate($user->id, 'email-change-step1', $this->email_new_change_resend_limit)) {
            return self::json(
                [],
                429,
                [
                    'message' => "You have already requested email change.\n"
                        . "You can request email change every 5 minutes.\n"
                        . "Please wait 5 minutes or less to start configuring another email address.",
                ]
            );
        }
        Mail::to($user)->queue(
            new UserEmailChangeConfirm1Old(
                Token::generate('email-change-step1', $this->email_change_token_time, $user->id, $request->all()),
                $request->input('uri_step1')
            )
        );
        DB::commit();

        return self::json([], 204);
    }

    public function emailChangeStep2($token)
    {
        DB::beginTransaction();
        if (false === $token = Token::check($token)) {
            DB::commit();

            return self::json([], 403, ['message' => 'Invalid or outdated token']);
        }
        $user = User::findOrFail($token['user_id']);
        if (User::withTrashed()->where('email', $token['payload']['email'])->count()) {
            DB::commit();

            return self::json([], 422, ['message' => 'This email already exists in our database']);
        }
        Mail::to($token['payload']['email'])->queue(
            new UserEmailChangeConfirm2New(
                Token::generate('email-change-step2', $this->email_change_token_time, $user->id, $token['payload']),
                $token['payload']['uri_step2']
            )
        );
        DB::commit();

        return self::json([], 204);
    }

    public function emailChangeStep3($token)
    {
        DB::beginTransaction();
        if (false === $token = Token::check($token)) {
            DB::commit();

            return self::json([], 403, ['message' => 'Invalid or outdated token']);
        }
        $user = User::findOrFail($token['user_id']);
        if (User::withTrashed()->where('email', $token['payload']['email'])->count()) {
            DB::commit();

            return self::json([], 422, ['message' => 'This email already exists in our database']);
        }
        $user->email = $token['payload']['email'];
        $user->email_confirmed_at = date('Y-m-d H:i:s');
        $user->save();
        DB::commit();

        return self::json($user->toArray(), 200);
    }

    /**
     * Log the user out of the application.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function check(Request $request)
    {
        return self::json(Auth::user()->toArray(), 200);
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

            return self::json(Auth::user()->load('AdserverWallet')->toArray(), 200);
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

    public function updateSelf(Request $request)
    {
        if (!Auth::check() && !$request->has('user.token')) {
            return self::json([], 401, ['message' => 'Required authenticated access or token authentication']);
        }

        DB::beginTransaction();
        if (Auth::check()) {
            $user = Auth::user();
            $token_authorization = false;
        } else {
            if (false === $token = Token::check($request->input('user.token'), null, 'password-recovery')) {
                DB::rollBack();

                return self::json([], 422, ['message' => 'Authentication token is invalid']);
            }
            $user = User::findOrFail($token['user_id']);
            $token_authorization = true;
        }

        $this->validateRequestObject($request, 'user', User::$rules);
        $user->fill($request->input('user'));

        if (!$request->has('user.password_new')) {
            $user->save();
            DB::commit();

            return self::json($user->toArray(), 200);
        }

        if ($token_authorization) {
            $user->password = $request->input('user.password_new');
            $user->save();
            DB::commit();

            return self::json($user->toArray(), 200);
        }

        if (!$request->has('user.password_old') || !$user->validPassword($request->input('user.password_old'))) {
            DB::rollBack();

            return self::json($user->toArray(), 422, ['password_old' => 'Old password is not valid']);
        }

        $user->password = $request->input('user.password_new');
        $user->save();
        DB::commit();

        return self::json($user->toArray(), 200);
    }

}
