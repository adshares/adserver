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

declare(strict_types=1);

namespace Adshares\Common\Infrastructure\Service;

use Adshares\Common\Domain\ValueObject\AccountId;

class CommunityFeeReader
{
    private const CE_LICENSE_ACCOUNT = '0001-00000024-FF89';
    private const CE_LICENSE_FEE = 0.01;

    public function getAddress(): AccountId
    {
        return new AccountId(self::CE_LICENSE_ACCOUNT);
    }

    public function getFee(): float
    {
        return self::CE_LICENSE_FEE;
    }
}
