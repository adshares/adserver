<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Http\Controllers\App;

use Adshares\Adserver\Http\Controllers\AppController;
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
            ->toArray()
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
