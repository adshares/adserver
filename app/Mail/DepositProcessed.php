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

use Adshares\Ads\Util\AdsConverter;
use Adshares\Common\Application\Model\Currency;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DepositProcessed extends Mailable
{
    use Queueable;
    use SerializesModels;

    private $amount;
    private $currency;

    public function __construct(int $amount, Currency $currency)
    {
        $this->amount = $amount;
        $this->currency = $currency;
    }

    public function build(): self
    {
        $formattedAmount = AdsConverter::clicksToAds($this->amount);
        if (Currency::ADS !== $this->currency) {
            $formattedAmount = substr($formattedAmount, 0, strpos($formattedAmount, '.') + 3);
        }
        $variables = [
            'amount' => $formattedAmount,
            'currency' => $this->currency->value,
        ];

        return $this->markdown('emails.deposit-processed')->with($variables);
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }
}
