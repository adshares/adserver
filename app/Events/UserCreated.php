<?php

namespace Adshares\Adserver\Events;

use Adshares\Adserver\Models\Notification;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserAdserverWallet;
use Adshares\Adserver\Models\UserSettings;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

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
            'userRole' => 'all',
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
