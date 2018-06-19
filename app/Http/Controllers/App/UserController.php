<?php

namespace Adshares\Adserver\Http\Controllers\App;

use Adshares\Adserver\Mail\UserEmailActivate;
use Adshares\Adserver\Models\User;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class UserController extends AppController
{
    public function register(Request $request)
    {
        $this->validateRequest('user', User::$rules);
        $user = User::create($request->input('user'));
        $user->email_confirm_token = md5(microtime());
        $user->save();
        Mail::to($user)->queue(new UserEmailActivate($user));
        //
        return self::json($user, 200);
    }

    public function emailActivate(Request $request, $token)
    {
        $user = User::where('email_confirm_token', $token)->whereNull('email_confirmed_at')->first();

        if (empty($user)) {
            return self::json(['token', $token], 401);
        }

        $user->email_confirmed_at = date('Y-m-d H:i:s');
        $user->save();
        return self::json(['user'=>$user], 200);
    }
}
