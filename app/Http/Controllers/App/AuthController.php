<?php

namespace Adshares\Adserver\Http\Controllers\App;

use Adshares\Adserver\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends AppController
{
    /**
     * Log the user out of the application.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function check(Request $request)
    {
        if (Auth::check()) {
            return self::json(Auth::user()->toArrayCamelize(), 200);
        }

        return self::json([], 401, ['message' => 'Not Authorized']);
    }

    /**
     * Log the user out of the application.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        if (Auth::guard()->attempt(
            $request->only('email', 'password'),
            $request->filled('remember')
        )) {
            $request->session()->regenerate();
            // $this->authenticated($request, $this->guard()->user());
            return self::json(Auth::user()->toArrayCamelize(), 200);
        }

        return self::json([], 401);
    }

    /**
     * Log the user out of the application.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        if (!Auth::check()) {
            return self::json([], 401, ['message' => 'Not Authorized']);
        }

        $this->guard()->logout();
        $request->session()->invalidate();

        return self::json([], 200);
    }
}
