<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Mail;

use Adshares\Ads\Util\AdsConverter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use function config;

class WithdrawalApproval extends Mailable
{
    use Queueable, SerializesModels;

    private $tokenId;

    private $amount;

    private $target;

    private $fee;

    public function __construct($tokenId, $amount, $fee, $target)
    {
        $this->tokenId = $tokenId;
        $this->amount = $amount;
        $this->target = $target;
        $this->fee = $fee;
    }

    public function build(): self
    {
        $variables = [
            'url' => config('app.adpanel_url')."/auth/withdrawal-confirmation/{$this->tokenId}",
            'amount' => AdsConverter::clicksToAds($this->amount),
            'fee' => AdsConverter::clicksToAds($this->fee),
            'total' => AdsConverter::clicksToAds($this->amount + $this->fee),
            'target' => $this->target,
        ];

        return $this->markdown('emails.withdrawal-approval')->with($variables);
    }
}
