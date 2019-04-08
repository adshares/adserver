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

namespace Adshares\Adserver\Tests\Repository\Common;

use Adshares\Adserver\Repository\Common\ExchangeRateRepositoryImpl;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Dto\FetchedExchangeRate;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use DateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class ExchangeRateRepositoryImplTest extends TestCase
{
    use RefreshDatabase;

    public function testExchangeRateRepositoryFetchWhileEmpty(): void
    {
        $exchangeRateRepository = new ExchangeRateRepositoryImpl();

        $this->expectException(ExchangeRateNotAvailableException::class);
        $exchangeRateRepository->fetchExchangeRate(new DateTime());
    }

    public function testExchangeRateRepositoryStoreAndFetch(): void
    {
        $exchangeRateRepository = new ExchangeRateRepositoryImpl();

        $dateTime = new DateTime();
        $dateTime->setTime((int)$dateTime->format('H'), (int)$dateTime->format('i'));

        $exchangeRate = new FetchedExchangeRate($dateTime, '1.3', 'USD');
        $exchangeRateRepository->storeExchangeRate($exchangeRate);
        $exchangeRateFromRepository = $exchangeRateRepository->fetchExchangeRate(new DateTime());

        $this->assertEquals($exchangeRate, $exchangeRateFromRepository);
    }
}
