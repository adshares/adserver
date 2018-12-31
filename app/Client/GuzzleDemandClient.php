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

declare(strict_types = 1);

namespace Adshares\Adserver\Client;

use Adshares\Common\Application\Service\SignatureVerifier;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Application\Service\Exception\EmptyInventoryException;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use Adshares\Supply\Domain\Factory\CampaignFactory;
use Adshares\Supply\Domain\Model\CampaignCollection;
use DateTime;
use GuzzleHttp\Client;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use function GuzzleHttp\json_decode;

final class GuzzleDemandClient implements DemandClient
{
    private const VERSION = '0.1';

    private const ALL_INVENTORY_ENDPOINT = '/adshares/inventory/list';

    private const PAYMENT_DETAILS_ENDPOINT = '/payment-details/{transactionId}/{accountAddress}/{date}/{signature}';

    /** @var SignatureVerifier */
    private $signatureVerifier;

    public function __construct(SignatureVerifier $signatureVerifier)
    {
        $this->signatureVerifier = $signatureVerifier;
    }

    public function fetchAllInventory(string $inventoryHost): CampaignCollection
    {
        $client = new Client([
            'headers' => ['Content-Type' => 'application/json', 'Cache-Control' => 'no-cache'],
            'base_uri' => $inventoryHost,
            'timeout' => 5,
        ]);

        $response = $client->get(self::ALL_INVENTORY_ENDPOINT);
        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();

        $this->validateResponse($statusCode, $body);

        try {
            $campaigns = json_decode($body, true);
        } catch (InvalidArgumentException $exception) {
            throw new RuntimeException('Invalid json data.');
        }

        $campaignsCollection = new CampaignCollection();
        foreach ($campaigns as $data) {
            $campaign = CampaignFactory::createFromArray($this->processData($data, $inventoryHost));
            $campaignsCollection->add($campaign);
        }

        return $campaignsCollection;
    }

    public function fetchPaymentDetails(string $host, string $transactionId): array
    {
        $client = new Client([
            'headers' => ['Content-Type' => 'application/json', 'Cache-Control' => 'no-cache'],
            'base_uri' => $host,
            'timeout' => 5,
        ]);

        $privateKey = (string)config('app.adshares_secret');
        $accountAddress = (string)config('app.adshares_address');
        $date = new DateTime();
        $signature = $this->signatureVerifier->create($privateKey, $transactionId, $accountAddress, $date);

        $dateFormatted = $date->format(DateTime::ATOM);

        $endpoint = str_replace(
            [
                '{transactionId}',
                '{accountAddress}',
                '{date}',
                '{signature}',
            ],
            [
                $transactionId,
                $accountAddress,
                $dateFormatted,
                $signature,
            ],
            self::PAYMENT_DETAILS_ENDPOINT
        );

        $response = $client->get($endpoint);
        $statusCode = $response->getStatusCode();
        $body = (string)$response->getBody();

        $this->validateResponse($statusCode, $body);

        try {
            $decoded = json_decode($body, true);
        } catch (InvalidArgumentException $exception) {
            throw new RuntimeException('Invalid json data.');
        }

        return $decoded;
    }

    private function validateResponse(int $statusCode, string $body): void
    {
        if ($statusCode !== Response::HTTP_OK) {
            throw new UnexpectedClientResponseException(sprintf('Unexpected response code `%s`.', $statusCode));
        }

        if (empty($body)) {
            throw new EmptyInventoryException('Empty list');
        }
    }

    private function processData(array $data, string $inventoryHost): array
    {
        $data['demand_id'] = Uuid::fromString($data['id']);
        $data['publisher_id'] = Uuid::fromString($data['publisher_id']);
        $data['date_start'] = DateTime::createFromFormat(DateTime::ATOM, $data['date_start']);
        $data['date_end'] = $data['date_end'] ? DateTime::createFromFormat(DateTime::ATOM, $data['date_end']) : null;

        $data['source_campaign'] = [
            'host' => $inventoryHost,
            'address' => $data['address'],
            'version' => self::VERSION,
            'created_at' => DateTime::createFromFormat(DateTime::ATOM, $data['created_at']),
            'updated_at' => DateTime::createFromFormat(DateTime::ATOM, $data['updated_at']),
        ];

        $data['created_at'] = new DateTime();
        $data['updated_at'] = new DateTime();
        $data['budget'] = (int)$data['budget'];
        $data['max_cpc'] = (int)$data['max_cpc'];
        $data['max_cpm'] = (int)$data['max_cpm'];

        unset($data['id']);

        return $data;
    }
}
