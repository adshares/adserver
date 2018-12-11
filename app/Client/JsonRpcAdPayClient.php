<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

use Adshares\Adserver\HttpClient\JsonRpc;
use Adshares\Adserver\HttpClient\JsonRpc\Procedure;
use Adshares\Demand\Application\Service\AdPay;

class JsonRpcAdPayClient implements AdPay
{
    private const METHOD_CAMPAIGN_UPDATE = 'campaign_update';

    private const METHOD_CAMPAIGN_DELETE = 'campaign_delete';

    private const METHOD_ADD_EVENTS = 'add_events';

    private const METHOD_GET_PAYMENTS = 'get_payments';

    /** @var JsonRpc */
    private $client;

    public function __construct(JsonRpc $client)
    {
        $this->client = $client;
    }

    /**
     * @param array $campaigns
     *
     * @throws JsonRpc\Exception\RemoteCallException
     * @throws JsonRpc\Exception\ResultException
     */
    public function updateCampaign(array $campaigns): void
    {
        $procedure = new Procedure(
            self::METHOD_CAMPAIGN_UPDATE,
            $campaigns
        );

        $this->client->call($procedure);
    }

    /**
     * @param array $campaignIds
     *
     * @throws JsonRpc\Exception\RemoteCallException
     * @throws JsonRpc\Exception\ResultException
     */
    public function deleteCampaign(array $campaignIds): void
    {
        $procedure = new Procedure(
            self::METHOD_CAMPAIGN_DELETE,
            $campaignIds
        );

        $this->client->call($procedure);
    }

    /**
     * @param array $events
     *
     * @throws JsonRpc\Exception\RemoteCallException
     * @throws JsonRpc\Exception\ResultException
     */
    public function addEvents(array $events): void
    {
        $procedure = new Procedure(
            self::METHOD_ADD_EVENTS,
            $events
        );

        $this->client->call($procedure);
    }

    /**
     * @param int $timestampFrom
     * @param int $timestampTo
     *
     * @return array
     * @throws JsonRpc\Exception\RemoteCallException
     * @throws JsonRpc\Exception\ResultException
     */
    public function getPayments(int $timestampFrom, int $timestampTo): array
    {
        $procedure = new Procedure(self::METHOD_GET_PAYMENTS, ['from' => $timestampFrom, 'to' => $timestampTo]);

        return $this->client->call($procedure)->toArray();
    }
}
