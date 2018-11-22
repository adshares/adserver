<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Client;

use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Supply\Domain\Factory\CampaignFactory;
use Adshares\Supply\Domain\Model\CampaignCollection;
use Adshares\Supply\Domain\Service\DemandClient;
use Adshares\Supply\Domain\Service\Exception\EmptyInventoryException;
use Adshares\Supply\Domain\Service\Exception\UnexpectedClientResponseException;
use DateTime;
use GuzzleHttp\Client;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use function GuzzleHttp\json_decode;

final class GuzzleDemandClient implements DemandClient
{
    const VERSION = '0.1';

    const ALL_INVENTORY_ENDPOINT = '/adshares/inventory/list';

    public function fetchAllInventory(string $inventoryHost): CampaignCollection
    {
        $client = new Client([
            'base_uri' => $inventoryHost,
            'timeout' => 5.0,
        ]);

        $response = $client->get(self::ALL_INVENTORY_ENDPOINT);
        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();

        $this->validateResponse($statusCode, $body);

        try {
            $campaigns = json_decode($body, true);
        } catch (InvalidArgumentException $exception) {
            throw new \RuntimeException('Invalid json data.');
        }

        $campaignsCollection = new CampaignCollection();
        foreach ($campaigns as $data) {
            $campaign = CampaignFactory::createFromArray($this->processData($data, $inventoryHost));
            $campaignsCollection->add($campaign);
        }

        return $campaignsCollection;
    }

    private function validateResponse(int $statusCode, string $body): void
    {
        if ($statusCode !== Response::HTTP_OK) {
            throw new UnexpectedClientResponseException(sprintf('Unexpected response code `%s`.', $statusCode));
        }

        if (empty($body)) {
            throw new EmptyInventoryException('Empty inventory list');
        }
    }

    private function processData(array $data, string $inventoryHost): array
    {
        $data['uuid'] = Uuid::fromString($data['uuid']);
        $data['date_start'] = DateTime::createFromFormat(DateTime::ISO8601, $data['date_start']);
        $data['date_end'] = DateTime::createFromFormat(DateTime::ISO8601, $data['date_end']);
        $data['created_at'] = DateTime::createFromFormat(DateTime::ISO8601, $data['created_at']);
        $data['updated_at'] = DateTime::createFromFormat(DateTime::ISO8601, $data['updated_at']);

        $data['source_host'] = [
            'host' => $inventoryHost,
            'address' => '',
            'version' => self::VERSION,
        ];

        return $data;
    }
}
