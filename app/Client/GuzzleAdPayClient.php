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

use Adshares\Common\Exception\RuntimeException;
use Adshares\Demand\Application\Dto\AdPayEvents;
use Adshares\Demand\Application\Service\AdPay;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;

class GuzzleAdPayClient implements AdPay
{
    private const URI_CAMPAIGNS = '/api/v1/campaigns';

    private const URI_PAYMENTS_TEMPLATE = '/api/v1/payments/%s';

    private const URI_VIEWS = '/api/v1/events/views';

    private const URI_CLICKS = '/api/v1/events/clicks';

    private const URI_CONVERSIONS = '/api/v1/events/conversions';

    /** @var Client */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function updateCampaign(array $campaigns): void
    {
        try {
            $this->client->post(
                self::URI_CAMPAIGNS,
                [
                    RequestOptions::JSON => $campaigns,
                ]
            );
        } catch (RequestException $exception) {
            throw new UnexpectedClientResponseException(
                sprintf(
                    '[ADPAY] Update campaigns on %s failed (%s).',
                    $this->client->getConfig()['base_uri'],
                    $exception->getMessage()
                ),
                $exception->getCode(),
                $exception
            );
        }
    }

    public function deleteCampaign(array $campaignIds): void
    {
        try {
            $this->client->delete(
                self::URI_CAMPAIGNS,
                [
                    RequestOptions::JSON => $campaignIds,
                ]
            );
        } catch (RequestException $exception) {
            throw new UnexpectedClientResponseException(
                sprintf(
                    '[ADPAY] Delete campaigns from %s failed (%s).',
                    $this->client->getConfig()['base_uri'],
                    $exception->getMessage()
                ),
                $exception->getCode(),
                $exception
            );
        }
    }

    public function addViews(AdPayEvents $events): void
    {
        $this->addEvents(self::URI_VIEWS, $events);
    }

    public function addClicks(AdPayEvents $events): void
    {
        $this->addEvents(self::URI_CLICKS, $events);
    }

    public function addConversions(AdPayEvents $events): void
    {
        $this->addEvents(self::URI_CONVERSIONS, $events);
    }

    private function addEvents(string $uri, AdPayEvents $events): void
    {
        try {
            $this->client->post(
                $uri,
                [
                    RequestOptions::JSON => $events->toArray(),
                ]
            );
        } catch (RequestException $exception) {
            throw new UnexpectedClientResponseException(
                sprintf(
                    '[ADPAY] Add events to %s%s failed (%s).',
                    $this->client->getConfig()['base_uri'],
                    $uri,
                    $exception->getMessage()
                ),
                $exception->getCode(),
                $exception
            );
        }
    }
    public function getPayments(int $timestamp, bool $recalculate = false, bool $force = false): array
    {
        $uri = sprintf(self::URI_PAYMENTS_TEMPLATE, $timestamp);

        try {
            $response = $this->client->get(
                $uri,
                [
                    'query' => [
                        'recalculate' => $recalculate,
                        'force' => $force,
                    ],
                ]
            );
        } catch (RequestException $exception) {
            throw new UnexpectedClientResponseException(
                sprintf(
                    '[ADPAY] Get payments from %s failed (%s).',
                    $this->client->getConfig()['base_uri'],
                    $exception->getMessage()
                ),
                $exception->getCode(),
                $exception
            );
        }

        $body = json_decode((string)$response->getBody(), true);

        if (!array_key_exists('payments', $body) || !is_array($payments = $body['payments'])) {
            throw new RuntimeException('Unexpected response format from the adpay');
        }

        return $payments;
    }
}
