<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer.  If not, see <https://www.gnu.org/licenses/>
 */

namespace Adshares\Adserver\Events;

use Adshares\Adserver\Models\Notification;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserAdserverWallet;
use Adshares\Adserver\Models\UserSettings;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserCreated
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user)
    {
        $this->createWelcomeNotification($user);
        $this->initNotificationsSettings($user);
        $this->initWallet($user);
    }

    protected function createWelcomeNotification(User $user)
    {
        $n = new Notification([
            'user_id' => $user->id,
            'user_role' => 'all',
            'type' => 'account',
            'title' => 'Welcome',
            'message' => 'Your account has been created',
        ]);
        $n->save();
    }

    protected function initNotificationsSettings(User $user)
    {
        $us = new UserSettings([
            'user_id' => $user->id,
            'type' => 'notifications',
            'payload' => UserSettings::$default_notifications,
        ]);
        $us->save();
    }

    protected function initWallet(User $user)
    {
        $uaw = new UserAdserverWallet();
        $uaw->user_id = $user->id;
        $uaw->payment_memo = 'User '.$user->uuid;
        $uaw->save();
    }
}
