<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
 *
 * This file is part of AdServer
 *
 * AdServer is free software: you can redistribute and/or modify it
 * under the terms of the GNU General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * AdServer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with AdServer. If not, see <https://www.gnu.org/licenses/>
 */

declare(strict_types = 1);

namespace Adshares\Adserver\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WalletFoundsEmail extends Mailable
{
    use Queueable;
    use SerializesModels;

    private const SUBJECT = 'Not enough founds on your account';

    private $transferValue;

    /** @var string */
    private $hotWalletAddress;

    public function __construct($transferValue, string $hotWalletAddress)
    {
        $this->transferValue = $transferValue;
        $this->hotWalletAddress = $hotWalletAddress;

        $this->subject(self::SUBJECT);
    }

    public function build()
    {
        return $this->markdown('emails.wallet-founds-email')->with(
            [
                'transferValue' => $this->transferValue,
                'address' => $this->hotWalletAddress,
            ]
        );
    }
}
