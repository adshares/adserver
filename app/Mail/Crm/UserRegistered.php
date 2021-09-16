<?php

/**
 * Copyright (c) 2018-2021 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Mail\Crm;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserRegistered extends Mailable
{
    use Queueable;
    use SerializesModels;

    private string $uuid;

    private string $email;

    private string $registrationDate;

    private ?string $refToken;

    public function __construct(string $uuid, string $email, string $registrationDate, ?string $refToken = null)
    {
        $this->uuid = $uuid;
        $this->email = $email;
        $this->registrationDate = $registrationDate;
        $this->refToken = $refToken;
    }

    public function build(): self
    {
        return $this->view('emails.crm.user-registered')->with(
            [
                'uuid' => $this->uuid,
                'email' => $this->email,
                'registrationDate' => $this->registrationDate,
                'refToken' => $this->refToken
            ]
        );
    }
}
