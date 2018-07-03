<?php

namespace Adshares\Adserver\Http\Controllers\App;

use Adshares\Adserver\Mail\UserEmailActivate;
use Adshares\Adserver\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class UsersController extends AppController
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('snake_casing');
    }

    public function add(Request $request)
    {
        $this->validateRequest($request, 'user', User::$rules_add);
        $user = new User($request->input('user'));
        $user->password = $request->input('user.password');
        $user->email = $request->input('user.email');
        $user->email_confirm_token = md5(openssl_random_pseudo_bytes(20));
        $user->save();

        Mail::to($user)->queue(new UserEmailActivate($user));

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
        $this->validateRequest($request, 'user', User::$rules);
        $user->fill($request->input('user'));
        $user->save();

        return self::json($user->toArrayCamelize(), 200);
    }

    public function emailActivate(Request $request)
    {
        $this->validateRequest($request, 'user', User::$rules_email_activate);

        $user = User::where(
            'email_confirm_token',
            $request->input('user.email_confirm_token')
        )->whereNull('email_confirmed_at')->first();

        if (empty($user)) {
            return self::json([], 401);
        }

        $user->email_confirmed_at = date('Y-m-d H:i:s');
        $user->save();

        return self::json($user->toArrayCamelize(), 200);
    }

    public function read(Request $request, $user_id)
    {
        // TODO check privileges
        $user = User::whereNull('deleted_at')->findOrFail($user_id);

        return self::json($user->toArrayCamelize());
    }
}
