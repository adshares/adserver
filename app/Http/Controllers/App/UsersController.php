<?php

namespace Adshares\Adserver\Http\Controllers\App;

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

class UsersController extends AppController
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
        $this->middleware('snake_casing')->except(['emailChangeStep1']);
    }

    public function add(Request $request)
    {
        $this->validateRequestObject($request, 'user', User::$rules_add);
        Validator::make($request->all(), ['uri' => 'required'])->validate();

        DB::beginTransaction();
        $user = User::register($request->input('user'));
        Mail::to($user)->queue(new UserEmailActivate(
            Token::generate('email-activate', $this->email_activation_token_time, $user->id),
            $request->input('uri')
        ))
        ;
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

    public function edit(Request $request)
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

            return self::json($user->toArrayCamelize(), 200);
        }

        if ($token_authorization) {
            $user->password = $request->input('user.password_new');
            $user->save();
            DB::commit();

            return self::json($user->toArrayCamelize(), 200);
        }

        if (!$request->has('user.password_old') || !$user->validPassword($request->input('user.password_old'))) {
            DB::rollBack();

            return self::json($user->toArrayCamelize(), 422, ['password_old' => 'Old password is not valid']);
        }

        $user->password = $request->input('user.password_new');
        $user->save();
        DB::commit();

        return self::json($user->toArrayCamelize(), 200);
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

        return self::json($user->toArrayCamelize(), 200);
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
                ['message' => 'You can request to resend email activation every 15 minutes. Please wait 15 minutes or less.']
            );
        }
        Mail::to($user)->queue(new UserEmailActivate(
            Token::generate('email-activate', $this->email_activation_token_time, $user->id),
            $request->input('uri')
        ))
        ;
        DB::commit();

        return self::json([], 204);
    }

    public function emailChangeStep1(Request $request)
    {
        Validator::make(
            $request->all(),
            ['email' => 'required|email', 'URIstep1' => 'required', 'URIstep2' => 'required']
        )->validate()
        ;
        if (User::withTrashed()->where('email', $request->input('email'))->count()) {
            return self::json([], 422, ['email' => 'This email already exists in our database']);
        }

        $user = Auth::user();
        DB::beginTransaction();
        if (!Token::canGenerate($user->id, 'email-change-step1', $this->email_new_change_resend_limit)) {
            return self::json(
                [],
                429,
                ['message' => "You have already requested email change.\nYou can request email change every 5 minutes.\nPlease wait 5 minutes or less to start configuring another email address."]
            );
        }
        Mail::to($user)->queue(new UserEmailChangeConfirm1Old(
            Token::generate('email-change-step1', $this->email_change_token_time, $user->id, $request->all()),
            $request->input('URIstep1')
        ))
        ;
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
        Mail::to($token['payload']['email'])->queue(new UserEmailChangeConfirm2New(
            Token::generate('email-change-step2', $this->email_change_token_time, $user->id, $token['payload']),
            $token['payload']['URIstep2']
        ))
        ;
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

        return self::json($user->toArrayCamelize(), 200);
    }

    public function read(Request $request, $user_id)
    {
        // TODO check privileges
        $user = User::findOrFail($user_id);

        return self::json($user->toArrayCamelize());
    }
}
