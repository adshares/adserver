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

namespace Adshares\Common\Application\Dto;

use DateTimeInterface;

class FetchedExchangeRate
{
    /** @var DateTimeInterface */
    private $dateTime;

    /** @var string */
    private $value;

    /** @var string */
    private $currency;

    public function __construct(DateTimeInterface $dateTime, string $value, string $currency)
    {
        $this->dateTime = $dateTime;
        $this->value = $value;
        $this->currency = $currency;
    }

    public function getDateTime(): DateTimeInterface
    {
        return $this->dateTime;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function toString(): string
    {
        return sprintf('%s::%s::%s', $this->dateTime->format(DateTimeInterface::ATOM), $this->value, $this->currency);
    }
}
