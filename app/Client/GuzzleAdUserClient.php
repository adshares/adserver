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

use Adshares\Common\Application\Dto\Taxonomy;
use Adshares\Common\Application\Factory\TaxonomyFactory;
use Adshares\Common\Application\Service\AdUser;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Dto\UserContext;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use function GuzzleHttp\json_decode;

final class GuzzleAdUserClient implements AdUser
{
    /** @var Client */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function fetchTargetingOptions(): Taxonomy
    {
        $response = $this->client->get('/getTaxonomy');
        $taxonomy = json_decode((string)$response->getBody(), true);

        return TaxonomyFactory::fromArray($taxonomy);
    }

    public function getUserContext(ImpressionContext $partialContext): UserContext
    {
        try {
            $response = $this->client->post(
                '/getData',
                [
                    'body' => $partialContext->adUserRequestBody(),
                ]
            );

            $context = json_decode((string)$response->getBody(), true);

            return UserContext::fromAdUserArray($context);
        } catch (ConnectException $exception) {
            return new UserContext([], 1, $partialContext->userId());
        }
    }
}
