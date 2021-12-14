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

use Adshares\Ads\Util\AdsConverter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WithdrawalSuccess extends Mailable
{
    use Queueable;
    use SerializesModels;

    private int $amount;
    private string $currency;
    private string $target;
    private int $fee;

    public function __construct(int $amount, string $currency, int $fee, string $target)
    {
        $this->amount = $amount;
        $this->currency = $currency;
        $this->target = $target;
        $this->fee = $fee;
    }

    public function build(): self
    {
        $variables = [
            'amount' => AdsConverter::clicksToAds($this->amount),
            'currency' => strtoupper($this->currency),
            'fee' => AdsConverter::clicksToAds($this->fee),
            'total' => AdsConverter::clicksToAds($this->amount + $this->fee),
            'target' => $this->target,
        ];
        return $this->markdown('emails.withdrawal-success')->with($variables);
    }
}
