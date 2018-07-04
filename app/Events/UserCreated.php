<?php

namespace Adshares\Adserver\Events;

use Adshares\Adserver\Models\User;
use Adshares\Adserver\Models\UserAdserverWallet;
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
        $uaw = new UserAdserverWallet();
        $uaw->user_id = $user->id;
        $uaw->payment_memo = 'User '.$user->uuid;
        $uaw->save();
    }
}
