<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserPasswordChangeConfirm extends Mailable
{
    use Queueable;
    use SerializesModels;

    protected string $token;
    protected string $uri;

    public function __construct(string $token, string $uri)
    {
        $this->subject('Confirm password change');
        $this->token = $token;
        $this->uri = $uri;
    }

    public function build()
    {
        return $this->markdown('emails.user-password-change-confirm')->with(
            [
                'token' => $this->token,
                'uri' => $this->uri,
            ]
        );
    }
}
