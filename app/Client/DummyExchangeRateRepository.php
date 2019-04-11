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

namespace Adshares\Adserver\Client;

use Adshares\Adserver\Utilities\DateUtils;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Application\Service\ExchangeRateRepository;
use DateTime;

class DummyExchangeRateRepository implements ExchangeRateRepository
{
    private const STABLE_RATE = '0.3333';

    public function fetchExchangeRate(DateTime $dateTime, string $currency = 'USD'): ExchangeRate
    {
        $date = DateUtils::getDateTimeRoundedToCurrentHour($dateTime);

        return new ExchangeRate($date, self::STABLE_RATE, $currency);
    }
}
