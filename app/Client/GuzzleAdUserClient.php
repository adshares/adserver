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
use Adshares\Common\Domain\Service\AdUserClient;
use GuzzleHttp\Client;
use function GuzzleHttp\json_decode;

final class GuzzleAdUserClient implements AdUserClient
{
    /** @var Client */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function fetchTaxonomy(): Taxonomy
    {
        $response = $this->client->get('/getTaxonomy');
        $taxonomy = json_decode($response->getBody()->getContents(), true);

        return TaxonomyFactory::fromArray($taxonomy);
    }
}
