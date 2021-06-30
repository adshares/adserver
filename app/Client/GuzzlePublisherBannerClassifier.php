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

use Adshares\Supply\Application\Dto\Classification\Collection;
use Adshares\Supply\Application\Service\BannerClassifier;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpFoundation\Response;

use function json_decode;

class GuzzlePublisherBannerClassifier implements BannerClassifier
{
    private const VERIFY_ENDPOINT = '/classify/fetch';

    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function fetchBannersClassification(array $bannerPublicIds): Collection
    {
        $body = json_encode($bannerPublicIds);

        try {
            $response = $this->client->post(self::VERIFY_ENDPOINT, ['body' => $body]);
        } catch (RequestException $exception) {
            $message = 'Could not connect to %s host (%s).';
            throw new UnexpectedClientResponseException(
                sprintf($message, $this->client->getConfig('base_uri'), $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }

        $statusCode = $response->getStatusCode();
        $responseBody = (string)$response->getBody();

        $this->validateResponse($statusCode, $responseBody);
        $decodedResponse = json_decode($responseBody, true);

        return $this->createClassificationCollection($decodedResponse);
    }

    private function createClassificationCollection(array $data): Collection
    {
        $collection = new Collection();
        foreach ($data as $bannerId => $classifications) {
            if (empty($classifications)) {
                $collection->addEmptyClassification($bannerId);

                continue;
            }

            foreach ($classifications as $classifier => $keywords) {
                $collection->addClassification($bannerId, $classifier, $keywords);
            }
        }

        return $collection;
    }

    private function validateResponse(int $statusCode, string $body): void
    {
        if ($statusCode !== Response::HTTP_OK) {
            throw new UnexpectedClientResponseException(sprintf('Unexpected response code `%s`.', $statusCode));
        }
    }
}
