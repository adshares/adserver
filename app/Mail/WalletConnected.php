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

use Adshares\Common\Domain\ValueObject\WalletAddress;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WalletConnected extends Mailable
{
    use Queueable;
    use SerializesModels;

    protected WalletAddress $address;

    public function __construct(WalletAddress $address)
    {
        $this->address = $address;
    }

    public function build(): self
    {
        return $this->subject('Your wallet has been successfully connected')
            ->markdown('emails.wallet-connected')
            ->with(
                [
                    'address' => $this->address->getAddress(),
                    'network' => $this->address->getNetwork(),
                ]
            );
    }
}
