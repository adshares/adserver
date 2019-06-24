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
use Adshares\Adserver\Models\EventLog;
use Adshares\Demand\Application\Service\AdPay;

final class JsonRpcAdPayClient implements AdPay
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

    public function updateCampaign(array $campaigns): void
    {
        $procedure = new Procedure(
            self::METHOD_CAMPAIGN_UPDATE,
            $campaigns
        );

        $this->client->call($procedure);
    }

    public function deleteCampaign(array $campaignIds): void
    {
        $procedure = new Procedure(
            self::METHOD_CAMPAIGN_DELETE,
            $campaignIds
        );

        $this->client->call($procedure);
    }

    public function addEvents(array $events): int
    {
        $filteredEvents = array_values(array_filter(
            $events,
            static function (array $event) {
                return $event['event_type'] !== EventLog::TYPE_REQUEST;
            }
        ));

        if (empty($filteredEvents)) {
            return 0;
        }

        $procedure = new Procedure(
            self::METHOD_ADD_EVENTS,
            $filteredEvents
        );

        $this->client->call($procedure)->isTrue();

        return count($filteredEvents);
    }

    public function getPayments(int $timestamp, bool $force): array
    {
        if ($force) {
            $procedure = new Procedure('debug_force_payment_recalculation', [['timestamp' => $timestamp]]);
            $this->client->call($procedure);
        }

        $procedure = new Procedure(self::METHOD_GET_PAYMENTS, [['timestamp' => $timestamp]]);
        $responseArray = $this->client->call($procedure)->toArray();

        return $responseArray['payments'];
    }
}
