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

namespace Adshares\Common\Domain\Model;

use Adshares\Common\Comparable;
use Adshares\Common\Domain;
use Adshares\Common\Domain\ValueObject\TransactionId;
use Adshares\Common\Identifiable;

final class Transaction implements Identifiable, Comparable
{
    /** @var TransactionId */
    private $id;

    public function __construct(TransactionId $id)
    {
        $this->id = $id;
    }

    public function id(): Domain\Id
    {
        return $this->id;
    }

    public function equals(object $other): bool
    {
        if ($other instanceof self) {
            return $this->id->equals($other->id);
        }

        return false;
    }
}
