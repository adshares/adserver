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

namespace Adshares\Adserver\Repository\Common;

use Adshares\Adserver\Models\ExchangeRate;
use Adshares\Common\Application\Dto\ExchangeRate as DomainExchangeRate;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use DateTime;

class EloquentExchangeRateRepository
{
    private const DATABASE_DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function fetchExchangeRate(DateTime $dateTime = null, string $currency = 'USD'): DomainExchangeRate
    {
        $exchangeRate =
            ExchangeRate::where('valid_at', '<=', (null === $dateTime) ? new DateTime() : $dateTime)
                ->where('currency', $currency)
                ->orderBy('valid_at', 'DESC')
                ->limit(1)
                ->first();

        if (!$exchangeRate) {
            throw new ExchangeRateNotAvailableException();
        }

        return new DomainExchangeRate(
            DateTime::createFromFormat(self::DATABASE_DATETIME_FORMAT, $exchangeRate->valid_at),
            (float)$exchangeRate->value,
            $exchangeRate->currency
        );
    }

    public function storeExchangeRate(DomainExchangeRate $fetchedExchangeRate)
    {
        (new ExchangeRate(
            [
                'valid_at' => $fetchedExchangeRate->getDateTime(),
                'value' => $fetchedExchangeRate->getValue(),
                'currency' => $fetchedExchangeRate->getCurrency(),
            ]
        ))->save();
    }
}
