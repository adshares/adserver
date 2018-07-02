<?php

namespace Adshares\Adserver\Http\Controllers\App;

use Adshares\Adserver\Mail\UserEmailActivate;
use Adshares\Adserver\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class UsersController extends AppController
{
    public function add(Request $request)
    {
        $this->validateRequest($request, 'user', User::$rules);
        $user = User::create($request->input('user'));
        $user->email_confirm_token = md5(openssl_random_pseudo_bytes(20));
        $user->save();

        Mail::to($user)->queue(new UserEmailActivate($user));

        $response = self::json($user, 201);
        $response->header('Location', route('app.users.read', ['user' => $user]));

        return $response;
    }

    public function browse(Request $request)
    {
        // TODO check privileges
        $users = User::whereNull('deleted_at')->get();

        return self::json($users);
    }

    public function edit(Request $request, $userId)
    {
    }

    public function delete(Request $request, $userId)
    {
        // TODO check privileges
        $user = User::whereNull('deleted_at')->findOrFail($userId);
        $user->deleted_at = new \DateTime();
        $user->save();

        return self::json(['message' => 'Successful deleted'], 200);
    }

    public function emailActivate(Request $request)
    {
        $this->validateRequest($request, 'user', User::$rules_email_activate);

        $user = User::where('email_confirm_token',
            $request->input('user.email_confirm_token'))->whereNull('email_confirmed_at')->first();

        if (empty($user)) {
            return self::json([], 401);
        }

        $user->email_confirmed_at = date('Y-m-d H:i:s');
        $user->save();

        return self::json($user, 200);
    }

    public function read(Request $request, $userId)
    {
        // TODO check privileges
        $user = User::whereNull('deleted_at')->findOrFail($userId);

        return self::json($user);
    }
}
