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

declare(strict_types=1);

namespace Adshares\Supply\Domain\ValueObject;

use Adshares\Supply\Domain\ValueObject\Exception\InvalidBudgetValueException;

final class Budget
{
    /** @var int */
    private $budget;

    /** @var float|null */
    private $maxCpc;

    /** @var float|null */
    private $maxCpm;

    public function __construct(float $budget, ?float $maxCpc, ?float $maxCpm)
    {
        if ($budget <= 0) {
            throw new InvalidBudgetValueException(sprintf(
                'Budget value: %s is invalid. The value Must be greater than 0',
                $budget
            ));
        }

        if ($maxCpc !== null && $maxCpc <= 0) {
            throw new InvalidBudgetValueException(sprintf(
                'Max Cpc value: %s is invalid. The value Must be greater than 0',
                $maxCpc
            ));
        }

        if ($maxCpm !== null && $maxCpm <= 0) {
            throw new InvalidBudgetValueException(sprintf(
                'Max Cpm value: %s is invalid. The value Must be greater than 0',
                $maxCpm
            ));
        }

        $total = $maxCpc + $maxCpm;

        if ($budget < $total) {
            throw new InvalidBudgetValueException(sprintf(
                'Budget `%s` must be greater than total value `%s`.',
                $budget,
                $total
            ));
        }

        $this->budget = $budget;
        $this->maxCpc = $maxCpc;
        $this->maxCpm = $maxCpm;
    }

    public function getBudget(): float
    {
        return $this->budget;
    }

    public function getMaxCpc(): ?float
    {
        return $this->maxCpc;
    }

    public function getMaxCpm(): ?float
    {
        return $this->maxCpm;
    }
}
