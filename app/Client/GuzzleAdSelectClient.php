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

namespace Adshares\Adserver\Client;

use Adshares\Adserver\Client\Mapper\CampaignToAdSelectMapper;
use Adshares\Supply\Domain\Model\Campaign;
use Adshares\Supply\Domain\Service\AdSelectClient;
use Adshares\Supply\Domain\Service\Exception\UnexpectedClientResponseException;
use GuzzleHttp\Client;
use Illuminate\Http\Response;
use InvalidArgumentException;
use RuntimeException;
use function GuzzleHttp\json_decode;
use function GuzzleHttp\json_encode;

class GuzzleAdSelectClient implements AdSelectClient
{
    const RPC_VERSION = '2.0';
    const UPDATE_METHOD = 'campaign_update';

    /** @var Client */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function exportInventory(Campaign $campaign): void
    {
        try {
            $body = json_encode(CampaignToAdSelectMapper::map($campaign));
        } catch (InvalidArgumentException $exception) {
            throw new RuntimeException('Invalid data format.');
        }

        $response = $this->client->post('', [
            'body' => $body,
        ]);
        $statusCode = $response->getStatusCode();
        $body = $response->getBody();

        $this->validateResponse($statusCode, $body);
    }

    private function validateResponse(int $statusCode, string $body): void
    {
        if ($statusCode !== Response::HTTP_OK) {
            throw new UnexpectedClientResponseException(sprintf('Unexpected response code `%s`.', $statusCode));
        }

        if (empty($body)) {
            throw new UnexpectedClientResponseException(sprintf('Empty response body.'));
        }

        $bodyDecoded = json_decode($body, true);

        if (!isset($bodyDecoded['result']) || !$bodyDecoded['result']) {
            throw new RuntimeException('Campaign has not been updated. Data format is not correct.');
        }
    }
}
