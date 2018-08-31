<?php

namespace Adshares\Adserver\Http\Controllers\App;

use Adshares\Adserver\Models\Notification;
use Illuminate\Support\Facades\Auth;

class NotificationsController extends AppController
{
    /**
     * Return adserver users notifications.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function read()
    {
        return self::json(Notification::where('user_id', Auth::user()->id)->get()->toArrayCamelize(), 200);
    }
}
