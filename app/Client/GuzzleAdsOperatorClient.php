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

namespace Adshares\Adserver\Client;

use Adshares\Adserver\Utilities\DateUtils;
use Adshares\Common\Application\Dto\ExchangeRate;
use Adshares\Common\Application\Service\Exception\ExchangeRateNotAvailableException;
use Adshares\Common\Application\Service\ExchangeRateRepository;
use DateTime;
use DateTimeInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

final class GuzzleAdsOperatorClient implements ExchangeRateRepository
{
    private const GET_ENDPOINT = '/api/v1/exchange-rate/{date}/{currency}';

    /** @var Client */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function fetchExchangeRate(DateTime $dateTime = null, string $currency = 'USD'): ExchangeRate
    {
        $dateTimeForComputation = (null === $dateTime) ? new DateTime() : clone $dateTime;

        $roundedDateTime = DateUtils::getDateTimeRoundedToCurrentHour($dateTimeForComputation);
        $uri = $this->getUri($dateTimeForComputation->modify('-1 hour'), $currency);

        try {
            $response = $this->client->get($uri);
        } catch (RequestException $exception) {
            $message = 'Could not fetch an exchange rate from the AdsOperator (%s/%s).';
            throw new ExchangeRateNotAvailableException(
                sprintf($message, $this->client->getConfig('base_uri'), $uri),
                $exception->getCode(),
                $exception
            );
        }

        $body = json_decode((string)$response->getBody());

        if (!isset($body->rate) || !is_numeric($body->rate)) {
            throw new ExchangeRateNotAvailableException('Unexpected response format from the AdsOperator');
        }

        return new ExchangeRate($roundedDateTime, $body->rate, $currency);
    }

    private function getUri(DateTime $dateTime, string $currency): string
    {
        return str_replace(
            ['{date}', '{currency}'],
            [$dateTime->format(DateTimeInterface::ATOM), $currency],
            self::GET_ENDPOINT
        );
    }
}
