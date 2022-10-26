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

namespace Adshares\Adserver\Http\Request\Filter;

use DateTimeInterface;

class DateFilter implements Filter
{
    private ?DateTimeInterface $from = null;
    private ?DateTimeInterface $to = null;

    public function __construct(private readonly string $name)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValues(): array
    {
        return [$this->from, $this->to];
    }

    public function getFrom(): ?DateTimeInterface
    {
        return $this->from;
    }

    public function getTo(): ?DateTimeInterface
    {
        return $this->to;
    }

    public function setFrom(?DateTimeInterface $from): void
    {
        $this->from = $from;
    }

    public function setTo(?DateTimeInterface $to): void
    {
        $this->to = $to;
    }
}
