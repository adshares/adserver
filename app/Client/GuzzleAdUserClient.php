<?php

/**
 * Copyright (c) 2018-2022 Adshares sp. z o.o.
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

use Adshares\Adserver\Http\Utils;
use Adshares\Common\Application\Dto\PageRank;
use Adshares\Common\Application\Dto\TaxonomyV2;
use Adshares\Common\Application\Factory\TaxonomyV2Factory;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Common\Exception\RuntimeException;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Dto\UserContext;
use Adshares\Supply\Application\Service\Exception\UnexpectedClientResponseException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

use function GuzzleHttp\json_decode;

final class GuzzleAdUserClient implements AdUser
{
    private Client $client;

    private const API_PATH = '/api/v2';

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function fetchPageRank(string $url): PageRank
    {
        $path = self::API_PATH . '/page-rank/' . urlencode($url);

        try {
            $response = $this->client->get($path);
            $body = json_decode((string)$response->getBody());
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new UnexpectedClientResponseException(
                sprintf('Cannot decode response from the AdUser for (%s)', $url)
            );
        } catch (RequestException $exception) {
            throw new RuntimeException(
                sprintf(
                    '{"url": "%s", "path": "%s",  "message": "%s"}',
                    $this->client->getConfig('base_uri'),
                    $path,
                    $exception->getMessage()
                )
            );
        }

        if (
            !isset($body->rank) || !isset($body->info) || !is_numeric($body->rank)
            || !in_array($body->info, self::PAGE_INFOS)
        ) {
            throw new UnexpectedClientResponseException(
                sprintf('Unexpected response format from the AdUser for (%s)', $url)
            );
        }

        return new PageRank($body->rank, $body->info);
    }

    public function fetchPageRankBatch(array $urls): array
    {
        return $this->postUrls(self::API_PATH . '/page-rank', $urls);
    }

    private function postUrls(string $path, array $urls): array
    {
        try {
            $response = $this->client->post(
                $path,
                [
                    RequestOptions::JSON => ['urls' => $urls],
                ]
            );
            $body = json_decode((string)$response->getBody(), true);
        } catch (InvalidArgumentException $invalidArgumentException) {
            throw new UnexpectedClientResponseException(
                sprintf('Cannot decode response from the AdUser for (%s)', join(',', $urls))
            );
        } catch (RequestException $exception) {
            throw new RuntimeException(
                sprintf(
                    '{"url": "%s", "path": "%s",  "message": "%s"}',
                    $this->client->getConfig('base_uri'),
                    $path,
                    $exception->getMessage()
                )
            );
        }

        return $body;
    }

    public function fetchTargetingOptions(): TaxonomyV2
    {
        $path = self::API_PATH . '/taxonomy';
        try {
            $response = $this->client->get($path);
            return TaxonomyV2Factory::fromJson((string)$response->getBody());
        } catch (RequestException $exception) {
            throw new RuntimeException(
                sprintf(
                    '{"url": "%s", "path": "%s",  "message": "%s"}',
                    $this->client->getConfig('base_uri'),
                    $path,
                    $exception->getMessage()
                )
            );
        }
    }

    public function getUserContext(ImpressionContext $context): UserContext
    {
        if (!$context->trackingId()) {
            return new UserContext(
                $context->keywords(),
                AdUser::HUMAN_SCORE_ON_MISSING_TID,
                AdUser::PAGE_RANK_ON_MISSING_TID,
                AdUser::PAGE_INFO_UNKNOWN,
                Utils::hexUserId()
            );
        }

        $path = sprintf(
            '%s/data/%s/%s',
            self::API_PATH,
            config('app.adserver_id'),
            $context->trackingId()
        );

        try {
            $response = $this->client->post(
                $path,
                ['form_params' => $context->adUserRequestBody()]
            );

            $body = json_decode((string)$response->getBody(), true);

            return UserContext::fromAdUserArray($body);
        } catch (GuzzleException $exception) {
            Log::error(sprintf('[ADUSER] error: %s', $exception->getMessage()));
            return new UserContext(
                $context->keywords(),
                AdUser::HUMAN_SCORE_ON_CONNECTION_ERROR,
                AdUser::PAGE_RANK_ON_CONNECTION_ERROR,
                AdUser::PAGE_INFO_UNKNOWN,
                Utils::hexUuidFromBase64UrlWithChecksum($context->trackingId())
            );
        }
    }

    public function reassessPageRankBatch(array $urls): array
    {
        return $this->postUrls(self::API_PATH . '/reassessment', $urls);
    }
}
