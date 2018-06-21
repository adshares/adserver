<?php

namespace Adshares\Adserver\Mail;

use Adshares\Adserver\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class UserEmailActivate extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var UserInvitation
     */
    protected $user;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.user-email-activate')->with([
            // 'name' => $this->user->name,
            'hash' => $this->user->email_confirm_token,
        ]);
    }
}
