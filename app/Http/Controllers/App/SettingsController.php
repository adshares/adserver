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
        $settings = UserSettings::where('user_id', Auth::user()->id)
            ->where('type', 'notifications')
            ->first()
            ->toArrayCamelize()
        ;

        $payload = [];
        foreach ($settings['payload'] as $name => $value) {
            $value['name'] = $name;
            $payload[] = $value;
        }
        $settings['payload'] = $payload;

        return self::json($settings, 200);
    }
}
