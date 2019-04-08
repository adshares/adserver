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

namespace Adshares\Adserver\Http\Controllers\Manager;

use Adshares\Adserver\Http\Controller;
use Adshares\Adserver\Http\Kernel;
use Adshares\Adserver\Mail\UserEmailActivate;
use Adshares\Adserver\Models\Token;
use Adshares\Adserver\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class UsersController extends Controller
{
    protected $email_activation_token_time = 24 * 60 * 60; // 24 hours

    protected $email_activation_resend_limit = 15 * 60; // 15 minutes

    protected $email_change_token_time = 60 * 60; // 1 hour

    protected $email_new_change_resend_limit = 5 * 60; // 1 minute

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware(Kernel::SNAKE_CASING)->except(['emailChangeStep1']);
    }

    public function add(Request $request)
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

        return self::json($user->toArray(), Response::HTTP_CREATED)
            ->header('Location', route('app.users.read', ['user_id' => $user->id]));
    }

    public function browse()
    {
        return User::all();
    }

    public function edit(Request $request)
    {
        if (!Auth::check() && !$request->has('user.token')) {
            return self::json(
                [],
                Response::HTTP_UNAUTHORIZED,
                ['message' => 'Required authenticated access or token authentication']
            );
        }

        DB::beginTransaction();
        if (Auth::check()) {
            $user = Auth::user();
            $token_authorization = false;
        } else {
            if (false === $token = Token::check($request->input('user.token'), null, 'password-recovery')) {
                DB::rollBack();

                return self::json(
                    [],
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    ['message' => 'Authentication token is invalid']
                );
            }
            $user = User::findOrFail($token['user_id']);
            $token_authorization = true;
        }

        $this->validateRequestObject($request, 'user', User::$rules);
        $user->fill($request->input('user'));

        if (!$request->has('user.password_new')) {
            $user->save();
            DB::commit();

            return self::json($user->toArray());
        }

        if ($token_authorization) {
            $user->password = $request->input('user.password_new');
            $user->save();
            DB::commit();

            return self::json($user->toArray());
        }

        if (!$request->has('user.password_old') || !$user->validPassword($request->input('user.password_old'))) {
            DB::rollBack();

            return self::json(
                $user->toArray(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['password_old' => 'Old password is not valid']
            );
        }

        $user->password = $request->input('user.password_new');
        $user->save();
        DB::commit();

        return self::json($user->toArray());
    }

    public function read($user_id)
    {
        // TODO check privileges
        $user = User::findOrFail($user_id);

        return self::json($user->toArray());
    }
}
