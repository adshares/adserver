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

use Adshares\Common\Domain\ValueObject\Url;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Common\UrlObject;
use Adshares\Supply\Application\Dto\Info;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Domain\Factory\CampaignFactory;
use Adshares\Supply\Domain\Model\CampaignCollection;
use DateTime;

final class DummyDemandClient implements DemandClient
{
    public $campaigns;

    public function __construct()
    {
        $this->campaigns = [
            CampaignFactory::createFromArray([
                'demand_id' => Uuid::v4(),
                'publisher_id' => Uuid::v4(),
                'landing_url' => 'http://adshares.pl',
                'date_start' => (new DateTime())->modify('-1 day'),
                'date_end' => (new DateTime())->modify('+2 days'),
                'created_at' => (new DateTime())->modify('-1 days'),
                'updated_at' => (new DateTime())->modify('-1 days'),
                'source_campaign' => [
                    'host' => 'localhost:8101',
                    'address' => '0001-00000001-0001',
                    'version' => '0.1',
                    'created_at' => new DateTime(),
                    'updated_at' => new DateTime(),
                ],
                'banners' => [
                    [
                        'serve_url' => 'http://localhost:8101/serve/1',
                        'click_url' => 'http://localhost:8101/click/1',
                        'view_url' => 'http://localhost:8101/view/1',
                        'type' => 'image',
                        'width' => 728,
                        'height' => 90,
                    ],
                    [
                        'serve_url' => 'http://localhost:8101/serve/1',
                        'click_url' => 'http://localhost:8101/click/1',
                        'view_url' => 'http://localhost:8101/view/1',
                        'type' => 'image',
                        'width' => 728,
                        'height' => 90,
                    ],
                    [
                        'serve_url' => 'http://localhost:8101/serve/1',
                        'click_url' => 'http://localhost:8101/click/1',
                        'view_url' => 'http://localhost:8101/view/1',
                        'type' => 'image',
                        'width' => 728,
                        'height' => 90,
                    ],
                ],
                'max_cpc' => 100000000000,
                'max_cpm' => 100000000000,
                'budget' => 1000000000000,
                'targeting_excludes' => [
                    'site' => [
                        'one',
                        'two',
                    ],
                ],
                'targeting_requires' => [],
            ]),
            CampaignFactory::createFromArray([
                'demand_id' => Uuid::v4(),
                'publisher_id' => Uuid::v4(),
                'landing_url' => 'http://adshares.net',
                'date_start' => (new DateTime())->modify('-10 day'),
                'date_end' => (new DateTime())->modify('+20 days'),
                'created_at' => (new DateTime())->modify('-10 days'),
                'updated_at' => (new DateTime())->modify('-1 days'),
                'source_campaign' => [
                    'host' => 'localhost:8101',
                    'address' => '0001-00000001-0001',
                    'version' => '0.1',
                    'created_at' => (new DateTime()),
                    'updated_at' => (new DateTime()),
                ],
                'banners' => [
                    [
                        'serve_url' => 'http://localhost:8101/serve/1',
                        'click_url' => 'http://localhost:8101/click/1',
                        'view_url' => 'http://localhost:8101/view/1',
                        'type' => 'image',
                        'width' => 728,
                        'height' => 90,
                    ],
                    [
                        'serve_url' => 'http://localhost:8101/serve/1',
                        'click_url' => 'http://localhost:8101/click/1',
                        'view_url' => 'http://localhost:8101/view/1',
                        'type' => 'image',
                        'width' => 728,
                        'height' => 90,
                    ],
                    [
                        'serve_url' => 'http://localhost:8101/serve/1',
                        'click_url' => 'http://localhost:8101/click/1',
                        'view_url' => 'http://localhost:8101/view/1',
                        'type' => 'image',
                        'width' => 728,
                        'height' => 90,
                    ],
                ],
                'max_cpc' => 1000000000000,
                'max_cpm' => 1000000000000,
                'budget' => 1000000000000000,
                'demand_host' => 'localhost:8101',
                'targeting_excludes' => [],
                'targeting_requires' => [],
            ]),
        ];
    }

    public function fetchAllInventory(string $inventoryUrl): CampaignCollection
    {
        return new CampaignCollection(...$this->campaigns);
    }

    public function fetchPaymentDetails(string $host, string $transactionId): array
    {
        return [
            [
                'event_id' => 'a98e2611cce44e6fb6ca82d9b9cbe017',
                'event_type' => 'view',
                'banner_id' => 'b22e19a3874847f4a6287d26deacd208',
                'zone_id' => 'a22e19a3874847f4a6287d26deacd208',
                'publisher_id' => 'fa9611d2d2f74e3f89c0e18b7c401891',
                'event_value' => 10,
            ],
            [
                'event_id' => '95a1170d739546799b959a9d0ca9b7c8',
                'event_type' => 'click',
                'banner_id' => '9c6edfaef7454af4a96cb434c85323ee',
                'zone_id' => '2c6edfaef7454af4a96cb434c85323ee',
                'publisher_id' => 'd5f5deefd010449ab0ee0e5e6b884090',
                'event_value' => 100,
            ],
        ];
    }

    public function fetchInfo(UrlObject $infoUrl): Info
    {
        return new Info(
            'ADSERVER',
            'ADSERVER DEMAND',
            '0.1',
            new Url('https://server.example.com/'),
            new Url('https://panel.example.com/'),
            new Url('https://example.com/privacy'),
            new Url('https://example.com/terms'),
            new Url('https://inventory.example.com/import'),
            'PUB', 'ADV'
        );
    }
}
