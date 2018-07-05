<?php

namespace Adshares\Adserver\Http\Controllers\App;

use Adshares\Adserver\Mail\AuthRecovery;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\Token;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AuthController extends AppController
{
    protected $password_recovery_resend_limit = 2 * 60; //2 minutes
    protected $password_recovery_token_time = 120 * 60; // 2 hours

    /**
     * Log the user out of the application.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function check(Request $request)
    {
        return self::json(Auth::user()->toArrayCamelize(), 200);
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
            return self::json(Auth::user()->load('AdserverWallet')->toArrayCamelize(), 200);
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
        Auth::guard()->logout();
        $request->session()->invalidate();

        return self::json([], 204);
    }

    /**
     * Log the user out of the application.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function recovery(Request $request)
    {
        Validator::make($request->all(), ['email' => 'required|email', 'uri' => 'required'])->validate();
        $user = User::where('email', $request->input('email'))->first();
        if (empty($user)) {
            return self::json([], 204);
        }
        DB::beginTransaction();
        if (!Token::canGenerate($user->id, 'password-recovery', $this->password_recovery_resend_limit)) {
            return self::json([], 204);
        }
        Mail::to($user)->queue(new AuthRecovery(
            Token::generate('password-recovery', $this->password_recovery_token_time, $user->id),
            $request->input('uri')
        ));
        DB::commit();

        return self::json([], 204);
    }
}
