<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

class TechnicalError extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly string $title, private readonly string $message = '')
    {
    }

    public function build(): Mailable
    {
        $this->subject = sprintf('Technical Error: %s', $this->title);
        return $this->markdown('emails.technical-error')->with(
            [
                'title' => $this->title,
                'message' => $this->message,
            ]
        );
    }
}
