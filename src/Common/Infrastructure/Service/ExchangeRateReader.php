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

namespace Adshares\Common\Infrastructure\Service;

use Adshares\Common\Application\Dto\FetchedExchangeRate;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Application\Service\ExchangeRateExternalProvider;
use Adshares\Common\Application\Service\ExchangeRateProvider;
use Adshares\Common\Application\Service\ExchangeRateRepository;
use DateTime;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class ExchangeRateReader implements ExchangeRateProvider
{
    private const MAX_ACCEPTABLE_CACHE_INTERVAL_IN_MINUTES = 60;

    private const MAX_ACCEPTABLE_INTERVAL_IN_HOURS = 24;

    /** @var ExchangeRateRepository */
    private $exchangeRateRepository;

    /** @var ExchangeRateExternalProvider */
    private $externalExchangeRateProvider;

    public function __construct(
        ExchangeRateRepository $exchangeRateRepository,
        ExchangeRateExternalProvider $externalExchangeRateProvider
    ) {
        $this->exchangeRateRepository = $exchangeRateRepository;
        $this->externalExchangeRateProvider = $externalExchangeRateProvider;
    }

    public function fetchExchangeRate(DateTime $dateTime, string $currency = 'USD'): FetchedExchangeRate
    {
        try {
            $exchangeRateRepository = $this->exchangeRateRepository->fetchExchangeRate($dateTime, $currency);

            if ($this->isCacheAcceptable($exchangeRateRepository)) {
                return $exchangeRateRepository;
            }
        } catch (ExchangeRateNotAvailableException $exception) {
            $exchangeRateRepository = null;
        }

        try {
            $exchangeRateExternal = $this->externalExchangeRateProvider->fetchExchangeRate($dateTime, $currency);
        } catch (ExchangeRateNotAvailableException $exception) {
            Log::warning(
                sprintf(
                    '[ExchangeRateReader] Cannot fetch exchange rate for %s: %s',
                    $dateTime->format(DateTime::ATOM),
                    $exception->getMessage()
                )
            );
            if (null === $exchangeRateRepository) {
                throw new ExchangeRateNotAvailableException();
            }

            $exchangeRateExternal = null;
        }

        if (null !== $exchangeRateExternal
            && (null === $exchangeRateRepository
                || $exchangeRateRepository->getDateTime() < $exchangeRateExternal->getDateTime())) {
            $exchangeRate = $exchangeRateExternal;

            try {
                $this->exchangeRateRepository->storeExchangeRate($exchangeRateExternal);
            } catch (QueryException $queryException) {
                Log::error(
                    sprintf('[ExchangeRateReader] Not able to store exchange rate: %s', $queryException->getMessage())
                );
            }
        } else {
            $exchangeRate = $exchangeRateRepository;
        }

        if ($this->isExchangeRateAcceptable($exchangeRate)) {
            return $exchangeRate;
        }

        throw new ExchangeRateNotAvailableException();
    }

    private function isCacheAcceptable(FetchedExchangeRate $exchangeRate): bool
    {
        $oldestAcceptableDateTime =
            (new DateTime())->modify(sprintf('-%d minutes', self::MAX_ACCEPTABLE_CACHE_INTERVAL_IN_MINUTES));

        return $exchangeRate->getDateTime() >= $oldestAcceptableDateTime;
    }

    private function isExchangeRateAcceptable(FetchedExchangeRate $exchangeRate): bool
    {
        $oldestAcceptableDateTime =
            (new DateTime())->modify(sprintf('-%d hours', self::MAX_ACCEPTABLE_INTERVAL_IN_HOURS));

        return $exchangeRate->getDateTime() >= $oldestAcceptableDateTime;
    }
}
