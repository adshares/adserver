<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Supply\Domain\Model;

use Adshares\Supply\Domain\Model\Exception\InvalidBudgetValueException;

final class Budget
{
    /** @var int */
    private $budget;

    /** @var int|null */
    private $maxCpc;

    /** @var int|null */
    private $maxCpm;

    public function __construct(int $budget, ?int $maxCpc, ?int $maxCpm)
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

        $total = (int)$maxCpc + (int)$maxCpm;

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
}
