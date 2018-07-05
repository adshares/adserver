<?php

namespace Adshares\Adserver\Http\Controllers\App;

use Adshares\Adserver\Mail\UserEmailActivate;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Token;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class UsersController extends AppController
{
    protected $email_token_time = 24 * 60 * 60; // 24 hours
    protected $email_activation_resend_limit = 15 * 60; // 15 minutes

    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('snake_casing');
    }

    public function add(Request $request)
    {
        $this->validateRequestObject($request, 'user', User::$rules_add);
        Validator::make($request->all(), ['uri' => 'required'])->validate();

        DB::beginTransaction();
        $user = User::register($request->input('user'));
        Mail::to($user)->queue(new UserEmailActivate(
            Token::generate('email-activate', $this->email_token_time, $user->id),
            $request->input('uri')
        ));
        DB::commit();

        $response = self::json($user->toArrayCamelize(), 201);
        $response->header('Location', route('app.users.read', ['user_id' => $user->id]));

        return $response;
    }

    public function browse(Request $request)
    {
        return self::json([], 501, ['message' => 'not yet implemented <3']);
        // TODO check privileges
        $users = User::with('AdserverWallet')->get();

        return self::json($users->toArrayCamelize());
    }

    public function delete(Request $request, $user_id)
    {
        return self::json([], 501, ['message' => 'not yet implemented <3']);
        // TODO check privileges
        // TODO reset email
        // TODO process
        $user = User::findOrFail($user_id);
        $user->delete();

        return self::json([], 204);
    }

    public function edit(Request $request, $user_id = null)
    {
        // TODO check privileges
        // Currently only for logged in user
        // has logic errors but works tmp
        // messy

        // $user = User::findOrFail($user_id);
        $user = Auth::user();
        $this->validateRequestObject($request, 'user', User::$rules);
        $user->fill($request->input('user'));
        $user->save();

        if (!$request->has('user.password_new')) {
            return self::json($user->toArrayCamelize(), 200);
        }

        if (!$request->has('user.password_old') && !$request->has('user.password_recovery_token')) {
            return self::json($user->toArrayCamelize(), 422, ['message' => 'Requires old password or token']);
        }

        if ($request->has('user.password_old')) {
            if ($user->validPassword($request->input('user.password_old'))) {
                return self::json($user->toArrayCamelize(), 422, ['password_old' => 'Wrong old password provided']);
            }
        }

        if ($request->has('user.password_recovery_token')) {//this is currently not needed, leave it for the possible future
            DB::beginTransaction();
            if (false === $token = Token::check($request->input('user.password_recovery_token'), $user->id)) {
                DB::rollBack();

                return self::json([], 401);
            }
            $user->password = $request->input('user.password_new');
            $user->save();
            DB::commit();

            return self::json($user->toArrayCamelize(), 422, ['message' => 'Requires old password or token']);
        }

        // $request->has('user.password_recovery_token')

        // $user->password = $request->input('user.password_new');
        $user->save();

        return self::json($user->toArrayCamelize(), 200);
    }

    public function emailActivate(Request $request)
    {
        Validator::make($request->all(), ['user.email_confirm_token' => 'required'])->validate();

        DB::beginTransaction();
        if (false === $token = Token::check($request->input('user.email_confirm_token'))) {
            return self::json([], 401);
        }
        $user = User::find($token['user_id']);
        if (empty($user)) {
            return self::json([], 401);
        }
        $user->email_confirmed_at = date('Y-m-d H:i:s');
        $user->save();
        DB::commit();

        return self::json($user->toArrayCamelize(), 200);
    }

    public function emailActivateResend(Request $request)
    {
        Validator::make($request->all(), ['uri' => 'required'])->validate();
        $user = Auth::user();
        DB::beginTransaction();
        if (!Token::canGenerate($user->id, 'email-activate', $this->email_activation_resend_limit)) {
            return self::json([], 400, ['message' => 'You can request 1 email activation every 15 minutes. Please wait.']);
        }
        Mail::to($user)->queue(new UserEmailActivate(
            Token::generate('email-activate', $this->email_token_time, $user->id),
            $request->input('uri')
        ));
        DB::commit();

        return self::json([], 204);
    }

    public function read(Request $request, $user_id)
    {
        // TODO check privileges
        $user = User::findOrFail($user_id);

        return self::json($user->toArrayCamelize());
    }
}
