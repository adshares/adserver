<?php

namespace Adshares\Adserver\Http\Controllers\App;

use Adshares\Adserver\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends AppController
{
    public function login(Request $request)
    {
        if (Auth::guard()->attempt(
            $request->only('email', 'password'),
            $request->filled('remember')
        )) {
            $request->session()->regenerate();
            // $this->authenticated($request, $this->guard()->user());
            return self::json(['user' => Auth::check() ? Auth::user() : false], 200);
        }

        return self::json([], 401);
    }

    public function check(Request $request)
    {
        return self::json(['user' => Auth::check() ? Auth::user() : false], 200);
    }
}
