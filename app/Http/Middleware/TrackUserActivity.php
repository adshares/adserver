<?php

namespace Adshares\Adserver\Http\Middleware;

use Adshares\Adserver\Models\User;
use Closure;
use Illuminate\Http\Request;

class TrackUserActivity
{
    public function handle(Request $request, Closure $next)
    {
        /** @var User $user */
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }
        if (null === $user->last_active_at || $user->last_active_at->isPast()) {
            $user->last_active_at = now();
            $user->save();
        }
        return $next($request);
    }
}
