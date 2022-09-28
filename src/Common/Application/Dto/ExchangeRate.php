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

declare(strict_types=1);

namespace Adshares\Common\Application\Dto;

use Adshares\Common\Application\Model\Currency;
use DateTime;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;

class ExchangeRate implements Arrayable
{
    public function __construct(
        private readonly DateTime $dateTime,
        private readonly float $value,
        private readonly string $currency
    ) {
    }

    public function getDateTime(): DateTime
    {
        return $this->dateTime;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function fromClick(int $amountInClicks): int
    {
        return (int)floor((float)$amountInClicks * $this->value);
    }

    public function toClick(int $amountInCurrency): int
    {
        return (int)floor($amountInCurrency / $this->value);
    }

    public function toString(): string
    {
        return sprintf('%s::%s::%s', $this->dateTime->format(DateTimeInterface::ATOM), $this->value, $this->currency);
    }

    public function toArray(): array
    {
        return [
            'valid_at' => $this->dateTime->format(DateTimeInterface::ATOM),
            'value' => $this->value,
            'currency' => $this->currency,
        ];
    }

    public static function ONE(Currency $currency): self
    {
        return new self(new DateTime(), 1.0, $currency->value);
    }
}
