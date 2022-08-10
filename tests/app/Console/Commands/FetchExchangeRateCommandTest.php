<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Tests\Console\Commands;

use Adshares\Adserver\Console\Locker;
use Adshares\Adserver\Models\Config;
use Adshares\Adserver\Repository\Common\EloquentExchangeRateRepository;
use Adshares\Adserver\Tests\Console\ConsoleTestCase;
use Adshares\Common\Application\Model\Currency;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Application\Service\ExchangeRateRepository;
use Illuminate\Database\QueryException;
use PDOException;

final class FetchExchangeRateCommandTest extends ConsoleTestCase
{
    private const COMMAND_SIGNATURE = 'ops:exchange-rate:fetch';

    /**
     * @dataProvider customProvider
     */
    public function testFetchExchangeRate(
        Currency $appCurrency,
        string $exchangeCurrencies,
        int $expectedStoreCallsCount
    ): void {
        Config::updateAdminSettings([
            Config::CURRENCY => $appCurrency->value,
            Config::EXCHANGE_CURRENCIES => $exchangeCurrencies,
        ]);
        $mockRepository = $this->createMock(EloquentExchangeRateRepository::class);
        $mockRepository->expects($this->exactly($expectedStoreCallsCount))->method('storeExchangeRate');

        $this->app->bind(
            EloquentExchangeRateRepository::class,
            function () use ($mockRepository) {
                return $mockRepository;
            }
        );

        $this->artisan(self::COMMAND_SIGNATURE)->assertSuccessful();
    }

    public function customProvider(): array
    {
        return [
            [Currency::ADS, '', 0],
            [Currency::ADS, 'USD', 1],
            [Currency::USD, '', 1],
            [Currency::USD, 'USD', 1],
            [Currency::USD, 'EUR,USD', 2],
        ];
    }

    public function testFetchExchangeRateNotAvailableException(): void
    {
        $mockRepository = $this->createMock(ExchangeRateRepository::class);
        $mockRepository->expects($this->once())->method('fetchExchangeRate')->willThrowException(
            new ExchangeRateNotAvailableException()
        );

        $this->app->bind(
            ExchangeRateRepository::class,
            function () use ($mockRepository) {
                return $mockRepository;
            }
        );

        $this->expectException(ExchangeRateNotAvailableException::class);
        $this->artisan(self::COMMAND_SIGNATURE);
    }

    public function testFetchExchangeRateStoreException(): void
    {
        $this->app->bind(
            EloquentExchangeRateRepository::class,
            function () {
                $previous = new PDOException();
                $previous->errorInfo = [0, 1040];
                $queryException = new QueryException('', [], $previous);
                $mock = self::createMock(EloquentExchangeRateRepository::class);
                $mock->method('storeExchangeRate')->willThrowException($queryException);
                return $mock;
            }
        );

        $this->expectException(QueryException::class);
        $this->artisan(self::COMMAND_SIGNATURE);
    }

    public function testFetchExchangeRateStoreDuplicateEntryException(): void
    {
        $this->app->bind(
            EloquentExchangeRateRepository::class,
            function () {
                $previous = new PDOException();
                $previous->errorInfo = [23000, 1062];
                $duplicatedEntryException = new QueryException('', [], $previous);
                $mock = self::createMock(EloquentExchangeRateRepository::class);
                $mock->method('storeExchangeRate')->willThrowException($duplicatedEntryException);
                return $mock;
            }
        );

        $this->artisan(self::COMMAND_SIGNATURE)->assertSuccessful();
    }

    public function testLock(): void
    {
        $lockerMock = $this->createMock(Locker::class);
        $lockerMock->expects(self::once())->method('lock')->willReturn(false);
        $this->instance(Locker::class, $lockerMock);

        $this->artisan(self::COMMAND_SIGNATURE)->assertSuccessful();
    }
}
