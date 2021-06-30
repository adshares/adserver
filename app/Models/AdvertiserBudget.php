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

declare(strict_types=1);

namespace Adshares\Adserver\Models;

final class AdvertiserBudget
{
    /** @var int */
    private $total;

    /** @var int */
    private $bonusable;

    public function __construct(int $total = 0, int $bonusable = 0)
    {
        $this->total = $total;
        $this->bonusable = $bonusable;
    }

    public function add(self $budget): self
    {
        return new self($this->total + $budget->total, $this->bonusable + $budget->bonusable);
    }

    public function total(): int
    {
        return $this->total;
    }

    public function bonusable(): int
    {
        return $this->bonusable;
    }
}
