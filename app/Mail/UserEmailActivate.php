<?php

namespace Adshares\Adserver\Mail;

use Adshares\Adserver\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserEmailActivate extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @var UserInvitation
     */
    protected $user;

    protected $uri;

    /**
     * Create a new message instance.
     *
     * @param User   $user
     * @param string $uri
     */
    public function __construct(User $user, $uri)
    {
        $this->user = $user;
        $this->uri = $uri;
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
            'uri' => $this->uri,
        ]);
    }
}
