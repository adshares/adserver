<?php

namespace Adshares\Adserver\Http\Controllers\App;

use Adshares\Adserver\Mail\UserEmailActivate;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Token;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class UsersController extends AppController
{
    protected $email_token_time = 24 * 60 * 60; // 24 hours

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
        $user = new User($request->input('user'));
        $user->password = $request->input('user.password');
        $user->email = $request->input('user.email');
        $user->save();
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
        // TODO check privileges
        $users = User::with('AdserverWallet')->whereNull('deleted_at')->get();

        return self::json($users->toArrayCamelize());
    }

    public function delete(Request $request, $user_id)
    {
        // TODO check privileges
        // TODO reset email
        // TODO process
        $user = User::whereNull('deleted_at')->findOrFail($user_id);
        $user->deleted_at = new \DateTime();
        $user->save();

        return self::json(['message' => 'successfully deleted'], 200);
    }

    public function edit(Request $request, $user_id)
    {
        // TODO check privileges
        $user = User::whereNull('deleted_at')->findOrFail($user_id);
        $this->validateRequestObject($request, 'user', User::$rules);
        $user->fill($request->input('user'));
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

    public function read(Request $request, $user_id)
    {
        // TODO check privileges
        $user = User::whereNull('deleted_at')->findOrFail($user_id);

        return self::json($user->toArrayCamelize());
    }
}
