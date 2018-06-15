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
        Mail::to($user)->queue(new UserEmailActivate($user));
        //
        return self::json($user, 200);
    }
}
