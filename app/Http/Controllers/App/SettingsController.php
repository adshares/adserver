<?php

namespace Adshares\Adserver\Http\Controllers\App;

use Adshares\Adserver\Models\UserSettings;
use Illuminate\Support\Facades\Auth;

class SettingsController extends AppController
{
    /**
     * Return adserver users notifications.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function readNotifications()
    {
        $o = UserSettings::where('user_id', Auth::user()->id)->where('type', 'notifications')->first()->toArrayCamelize();
        $p = [];
        foreach ($o['payload'] as $n => $v) {
            $v['name'] = $n;
            $p[] = $v;
        }
        $o['payload'] = $p;

        return self::json($o, 200);
    }
}
