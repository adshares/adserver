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

namespace Adshares\Adserver\Mail;

use Adshares\Common\Domain\ValueObject\WalletAddress;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WalletConnectConfirm extends Mailable
{
    use Queueable;
    use SerializesModels;

    protected WalletAddress $address;
    protected string $token;
    protected string $uri;

    public function __construct(WalletAddress $address, string $token, string $uri)
    {
        $this->address = $address;
        $this->token = $token;
        $this->uri = $uri;
    }

    public function build(): self
    {
        return $this->markdown('emails.wallet-connect-confirm')->with(
            [
                'address' => $this->address->getAddress(),
                'network' => $this->address->getNetwork(),
                'token' => $this->token,
                'uri' => $this->uri,
            ]
        );
    }
}
