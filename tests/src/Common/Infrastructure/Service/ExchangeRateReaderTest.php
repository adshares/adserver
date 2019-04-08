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

namespace Adshares\Tests\Common\Infrastructure\Service;

use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Dto\FetchedExchangeRate;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Application\Service\ExchangeRateExternalProvider;
use Adshares\Common\Application\Service\ExchangeRateRepository;
use Adshares\Common\Infrastructure\Service\ExchangeRateReader;
use DateTime;

class ExchangeRateReaderTest extends TestCase
{
    public function testExchangeRateReaderEmptyRepositoryAndProviderFail(): void
    {
        $exchangeRateExternalProvider = $this->createMock(ExchangeRateExternalProvider::class);
        $exchangeRateExternalProvider->expects($this->once())->method('fetchExchangeRate')->willThrowException(
            new ExchangeRateNotAvailableException()
        );

        $exchangeRateRepository = $this->createMock(ExchangeRateRepository::class);
        $exchangeRateRepository->expects($this->once())->method('fetchExchangeRate')->willThrowException(
            new ExchangeRateNotAvailableException()
        );
        $exchangeRateRepository->expects($this->never())->method('storeExchangeRate');

        $exchangeRateReader = new ExchangeRateReader($exchangeRateRepository, $exchangeRateExternalProvider);

        $this->expectException(ExchangeRateNotAvailableException::class);
        $exchangeRateReader->fetchExchangeRate(new DateTime());
    }

    public function testExchangeRateReaderEmptyRepositoryAndProviderSuccess(): void
    {
        $exchangeRateValue = '1';
        $exchangeRateDateTime = null;

        $exchangeRateExternalProvider = $this->createMock(ExchangeRateExternalProvider::class);
        $exchangeRateExternalProvider->expects($this->once())->method('fetchExchangeRate')->willReturnCallback(
            function (DateTime $dateTime) use ($exchangeRateValue, &$exchangeRateDateTime) {
                $exchangeRateDateTime = (clone $dateTime)->setTime((int)$dateTime->format('H'), 0);

                return new FetchedExchangeRate($exchangeRateDateTime, $exchangeRateValue, 'USD');
            }
        );

        $exchangeRateRepository = $this->createMock(ExchangeRateRepository::class);
        $exchangeRateRepository->expects($this->once())->method('fetchExchangeRate')->willThrowException(
            new ExchangeRateNotAvailableException()
        );
        $exchangeRateRepository->expects($this->once())->method('storeExchangeRate');

        $exchangeRateReader = new ExchangeRateReader($exchangeRateRepository, $exchangeRateExternalProvider);

        $exchangeRate = $exchangeRateReader->fetchExchangeRate(new DateTime());
        $this->assertEquals($exchangeRateValue, $exchangeRate->getValue());
        $this->assertEquals($exchangeRateDateTime, $exchangeRate->getDateTime());
    }

    public function testExchangeRateReaderEmptyRepositoryAndProviderOldValue(): void
    {
        $exchangeRateValue = '1';
        $exchangeRateDateTime = (new DateTime())->modify('-1 year');

        $exchangeRateExternalProvider = $this->createMock(ExchangeRateExternalProvider::class);
        $exchangeRateExternalProvider->expects($this->once())->method('fetchExchangeRate')->willReturnCallback(
            function () use ($exchangeRateValue, $exchangeRateDateTime) {
                return new FetchedExchangeRate($exchangeRateDateTime, $exchangeRateValue, 'USD');
            }
        );

        $exchangeRateRepository = $this->createMock(ExchangeRateRepository::class);
        $exchangeRateRepository->expects($this->once())->method('fetchExchangeRate')->willThrowException(
            new ExchangeRateNotAvailableException()
        );
        $exchangeRateRepository->expects($this->once())->method('storeExchangeRate');

        $exchangeRateReader = new ExchangeRateReader($exchangeRateRepository, $exchangeRateExternalProvider);

        $this->expectException(ExchangeRateNotAvailableException::class);
        $exchangeRateReader->fetchExchangeRate(new DateTime());
    }

    public function testExchangeRateReaderSuccessRepository(): void
    {
        $exchangeRateValue = '1';
        $exchangeRateDateTime = null;

        $exchangeRateExternalProvider = $this->createMock(ExchangeRateExternalProvider::class);
        $exchangeRateExternalProvider->expects($this->never())->method('fetchExchangeRate');

        $exchangeRateRepository = $this->createMock(ExchangeRateRepository::class);
        $exchangeRateRepository->expects($this->once())->method('fetchExchangeRate')->willReturnCallback(
            function (DateTime $dateTime) use ($exchangeRateValue, &$exchangeRateDateTime) {
                $exchangeRateDateTime = (clone $dateTime)->setTime((int)$dateTime->format('H'), 0);

                return new FetchedExchangeRate($exchangeRateDateTime, $exchangeRateValue, 'USD');
            }
        );
        $exchangeRateRepository->expects($this->never())->method('storeExchangeRate');

        $exchangeRateReader = new ExchangeRateReader($exchangeRateRepository, $exchangeRateExternalProvider);

        $exchangeRate = $exchangeRateReader->fetchExchangeRate(new DateTime());
        $this->assertEquals($exchangeRateValue, $exchangeRate->getValue());
        $this->assertEquals($exchangeRateDateTime, $exchangeRate->getDateTime());
    }

    public function testExchangeRateReaderRepositoryOldValueAndProviderSuccess(): void
    {
        $exchangeRateValue = '1';
        $exchangeRateDateTime = null;

        $exchangeRateExternalProvider = $this->createMock(ExchangeRateExternalProvider::class);
        $exchangeRateExternalProvider->expects($this->once())->method('fetchExchangeRate')->willReturnCallback(
            function (DateTime $dateTime) use ($exchangeRateValue, &$exchangeRateDateTime) {
                $exchangeRateDateTime = (clone $dateTime)->setTime((int)$dateTime->format('H'), 0);

                return new FetchedExchangeRate($exchangeRateDateTime, $exchangeRateValue, 'USD');
            }
        );

        $exchangeRateRepository = $this->createMock(ExchangeRateRepository::class);
        $exchangeRateRepository->expects($this->once())->method('fetchExchangeRate')->willReturnCallback(
            function () {
                $exchangeRateValue = '1';
                $exchangeRateDateTime = (new DateTime())->modify('-1 year');

                return new FetchedExchangeRate($exchangeRateDateTime, $exchangeRateValue, 'USD');
            }
        );
        $exchangeRateRepository->expects($this->once())->method('storeExchangeRate');

        $exchangeRateReader = new ExchangeRateReader($exchangeRateRepository, $exchangeRateExternalProvider);

        $exchangeRate = $exchangeRateReader->fetchExchangeRate(new DateTime());
        $this->assertEquals($exchangeRateValue, $exchangeRate->getValue());
        $this->assertEquals($exchangeRateDateTime, $exchangeRate->getDateTime());
    }
}
