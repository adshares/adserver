<?php

namespace Adshares\Adserver\Http\Controllers\App;

use Adshares\Adserver\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends AppController
{
    public function check(Request $request)
    {
        if (Auth::check()) {
            return self::json(Auth::user(), 200);
        }

        return self::json([], 401, ['message' => 'Not Authorized']);
    }

    public function login(Request $request)
    {
        if (Auth::guard()->attempt(
            $request->only('email', 'password'),
            $request->filled('remember')
        )) {
            $request->session()->regenerate();
            // $this->authenticated($request, $this->guard()->user());
            return self::json(Auth::user(), 200);
        }

        return self::json([], 401);
    }
}
