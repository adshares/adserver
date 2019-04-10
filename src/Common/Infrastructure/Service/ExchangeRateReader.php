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

use Adshares\Adserver\Utilities\DateUtils;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Application\Service\ExchangeRateRepository;
use Adshares\Common\Application\Service\ExchangeRateRepositoryStorable;
use DateTime;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class ExchangeRateReader
{
    private const MAX_ACCEPTABLE_INTERVAL_IN_HOURS = 24;

    /** @var ExchangeRateRepositoryStorable */
    private $repositoryStorable;

    /** @var ExchangeRateRepository */
    private $repositoryRemote;

    public function __construct(
        ExchangeRateRepositoryStorable $repositoryStorable,
        ExchangeRateRepository $repositoryRemote
    ) {
        $this->repositoryStorable = $repositoryStorable;
        $this->repositoryRemote = $repositoryRemote;
    }

    public function fetchExchangeRate(DateTime $dateTime, string $currency = 'USD'): ExchangeRate
    {
        try {
            $exchangeRateFromStorage = $this->repositoryStorable->fetchExchangeRate($dateTime, $currency);

            if (DateUtils::areTheSameHour($exchangeRateFromStorage->getDateTime(), $dateTime)) {
                return $exchangeRateFromStorage;
            }
        } catch (ExchangeRateNotAvailableException $exception) {
            $exchangeRateFromStorage = null;
        }

        try {
            $exchangeRateRemote = $this->repositoryRemote->fetchExchangeRate($dateTime, $currency);
        } catch (ExchangeRateNotAvailableException $exception) {
            Log::warning(
                sprintf(
                    '[ExchangeRateReader] Cannot fetch exchange rate for %s: %s',
                    $dateTime->format(DateTime::ATOM),
                    $exception->getMessage()
                )
            );
            if (null === $exchangeRateFromStorage) {
                throw new ExchangeRateNotAvailableException();
            }

            $exchangeRateRemote = null;
        }

        if (null !== $exchangeRateRemote
            && (null === $exchangeRateFromStorage
                || $exchangeRateFromStorage->getDateTime() < $exchangeRateRemote->getDateTime())) {
            $exchangeRate = $exchangeRateRemote;

            try {
                $this->repositoryStorable->storeExchangeRate($exchangeRateRemote);
            } catch (QueryException $queryException) {
                Log::error(
                    sprintf('[ExchangeRateReader] Not able to store exchange rate: %s', $queryException->getMessage())
                );
            }
        } else {
            $exchangeRate = $exchangeRateFromStorage;
        }

        if ($this->isExchangeRateAcceptable($exchangeRate)) {
            return $exchangeRate;
        }

        throw new ExchangeRateNotAvailableException();
    }

    private function isExchangeRateAcceptable(ExchangeRate $exchangeRate): bool
    {
        $oldestAcceptableDateTime =
            (new DateTime())->modify(sprintf('-%d hours', self::MAX_ACCEPTABLE_INTERVAL_IN_HOURS));

        return $exchangeRate->getDateTime() >= $oldestAcceptableDateTime;
    }
}
