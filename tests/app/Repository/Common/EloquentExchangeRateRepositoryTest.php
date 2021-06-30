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

namespace Adshares\Adserver\Tests\Repository\Common;

use Adshares\Adserver\Repository\Common\EloquentExchangeRateRepository;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use DateTime;

final class EloquentExchangeRateRepositoryTest extends TestCase
{
    public function testExchangeRateRepositoryFetchWhileEmpty(): void
    {
        $repository = new EloquentExchangeRateRepository();

        $this->expectException(ExchangeRateNotAvailableException::class);
        $repository->fetchExchangeRate();
    }

    public function testExchangeRateRepositoryStoreAndFetch(): void
    {
        $repository = new EloquentExchangeRateRepository();

        $dateTime = new DateTime();
        $dateTime->setTime((int)$dateTime->format('H'), (int)$dateTime->format('i'));

        $exchangeRate = new ExchangeRate($dateTime, 1.3, 'USD');
        $repository->storeExchangeRate($exchangeRate);
        $exchangeRateFromRepository = $repository->fetchExchangeRate();

        $this->assertEquals($exchangeRate, $exchangeRateFromRepository);
    }
}
