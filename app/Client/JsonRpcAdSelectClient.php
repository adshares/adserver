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

use Adshares\Adserver\Client\Mapper\CampaignToAdSelectMapper;
use Adshares\Adserver\HttpClient\JsonRpc;
use Adshares\Adserver\HttpClient\JsonRpc\Procedure;
use Adshares\Supply\Application\Dto\FoundBanners;
use Adshares\Supply\Application\Dto\ImpressionContext;
use Adshares\Supply\Application\Service\BannerFinder;
use Adshares\Supply\Application\Service\InventoryExporter;
use Adshares\Supply\Domain\Model\Campaign;

final class JsonRpcAdSelectClient implements BannerFinder, InventoryExporter
{
    private const METHOD_CAMPAIGN_UPDATE = 'campaign_update';
    private const METHOD_BANNER_SELECT = 'banner_select';

    /** @var JsonRpc */
    private $client;

    public function __construct(JsonRpc $client)
    {
        $this->client = $client;
    }

    public function findBanners(ImpressionContext $context): FoundBanners
    {
        $procedure = new Procedure(
            self::METHOD_BANNER_SELECT,
            $context->jsonRpcParams()
        );
        $result = $this->client->call($procedure);

        return new FoundBanners($result->toArray());
    }

    public function exportInventory(Campaign $campaign): void
    {
        $procedure = new Procedure(
            self::METHOD_CAMPAIGN_UPDATE,
            CampaignToAdSelectMapper::map($campaign)
        );

        $this->client->call($procedure);
    }
}
