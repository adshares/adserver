<?php

namespace Adshares\Adserver\Mail;

use Adshares\Adserver\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserEmailChangeConfirm2New extends Mailable
{
    use Queueable, SerializesModels;

    protected $token;
    protected $uri;

    /**
     * Create a new message instance.
     *
     * @param string $token
     * @param string $uri
     */
    public function __construct($token, $uri)
    {
        $this->token = $token;
        $this->uri = $uri;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.user-email-change-confirm-2-new')->with([
            'token' => $this->token,
            'uri' => $this->uri,
        ]);
    }
}
