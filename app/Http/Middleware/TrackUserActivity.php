<?php

namespace Adshares\Adserver\Http\Middleware;

use Adshares\Adserver\Models\NotificationEmailLog;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\ViewModel\NotificationEmailCategory;
use Closure;
use DateTimeImmutable;
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
        if (null === $user->last_active_at || $user->last_active_at < new DateTimeImmutable('-5 minutes')) {
            $user->last_active_at = now();
            $user->save();
            NotificationEmailLog::fetch($user->id, NotificationEmailCategory::InactiveUserExtend)?->invalidate();
        }
        return $next($request);
    }
}
