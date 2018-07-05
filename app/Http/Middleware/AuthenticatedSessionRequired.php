<?php

namespace Adshares\Adserver\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class NotAuthenticatedSessionRequired
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     * @param string|null              $guard
     *
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (!Auth::guard($guard)->check()) {
            return self::json([], 401, ['message' => 'Required user access']);
        }

        return $next($request);
    }
}
