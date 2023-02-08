<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

namespace Adshares\Common\Application\Dto;

use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Exception\InvalidArgumentException;

class CommunityFee
{
    private function __construct(
        private readonly float $fee,
        private readonly AccountId $account,
    ) {
    }

    public static function fromArray(array $data): self
    {
        self::validate($data);
        return new self(
            $data['demandFee'],
            new AccountId($data['accountAddress']),
        );
    }

    private static function validate(array $data): void
    {
        if (!isset($data['demandFee']) || !is_float($data['demandFee'])) {
            throw new InvalidArgumentException('Invalid `demandFee`');
        }
        if (
            !isset($data['accountAddress'])
            || !is_string($data['accountAddress'])
            || !AccountId::isValid($data['accountAddress'], true)
        ) {
            throw new InvalidArgumentException('Invalid `accountAddress`');
        }
    }

    public function getFee(): float
    {
        return $this->fee;
    }

    public function getAccount(): AccountId
    {
        return $this->account;
    }
}
