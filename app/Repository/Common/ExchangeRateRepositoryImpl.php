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

namespace Adshares\Adserver\Repository\Common;

use Adshares\Adserver\Models\ExchangeRate;
use Adshares\Common\Application\Dto\FetchedExchangeRate;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Application\Service\ExchangeRateRepository;
use DateTime;

class ExchangeRateRepositoryImpl implements ExchangeRateRepository
{
    private const DATABASE_DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function fetchExchangeRate(DateTime $dateTime, string $currency = 'USD'): FetchedExchangeRate
    {
        $exchangeRate =
            ExchangeRate::where('valid_at', '<=', $dateTime)
                ->where('currency', $currency)
                ->orderBy('valid_at', 'DESC')
                ->limit(1)
                ->first();

        if (!$exchangeRate) {
            throw new ExchangeRateNotAvailableException();
        }

        return new FetchedExchangeRate(
            DateTime::createFromFormat(self::DATABASE_DATETIME_FORMAT, $exchangeRate->valid_at),
            $exchangeRate->value,
            $exchangeRate->currency
        );
    }

    public function storeExchangeRate(FetchedExchangeRate $fetchedExchangeRate)
    {
        ExchangeRate::create($fetchedExchangeRate)->save();
    }
}
