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

namespace Adshares\Mock\Client;

use Adshares\Common\Domain\ValueObject\AccountId;
use Adshares\Common\Domain\ValueObject\Email;
use Adshares\Common\Domain\ValueObject\Url;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Common\UrlInterface;
use Adshares\Supply\Application\Dto\Info;
use Adshares\Supply\Application\Dto\InfoStatistics;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Domain\Factory\CampaignFactory;
use Adshares\Supply\Domain\Model\CampaignCollection;
use DateTime;

use function array_chunk;
use function floor;

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
                        'demand_banner_id' => Uuid::v4(),
                        'serve_url' => 'http://localhost:8101/serve/1',
                        'click_url' => 'http://localhost:8101/click/1',
                        'view_url' => 'http://localhost:8101/view/1',
                        'type' => 'image',
                        'size' => '728x90',
                    ],
                    [
                        'demand_banner_id' => Uuid::v4(),
                        'serve_url' => 'http://localhost:8101/serve/1',
                        'click_url' => 'http://localhost:8101/click/1',
                        'view_url' => 'http://localhost:8101/view/1',
                        'type' => 'image',
                        'size' => '728x90',
                    ],
                    [
                        'demand_banner_id' => Uuid::v4(),
                        'serve_url' => 'http://localhost:8101/serve/1',
                        'click_url' => 'http://localhost:8101/click/1',
                        'view_url' => 'http://localhost:8101/view/1',
                        'type' => 'image',
                        'size' => '728x90',
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
                        'demand_banner_id' => Uuid::v4(),
                        'serve_url' => 'http://localhost:8101/serve/1',
                        'click_url' => 'http://localhost:8101/click/1',
                        'view_url' => 'http://localhost:8101/view/1',
                        'type' => 'image',
                        'size' => '728x90',
                    ],
                    [
                        'demand_banner_id' => Uuid::v4(),
                        'serve_url' => 'http://localhost:8101/serve/1',
                        'click_url' => 'http://localhost:8101/click/1',
                        'view_url' => 'http://localhost:8101/view/1',
                        'type' => 'image',
                        'size' => '728x90',
                    ],
                    [
                        'demand_banner_id' => Uuid::v4(),
                        'serve_url' => 'http://localhost:8101/serve/1',
                        'click_url' => 'http://localhost:8101/click/1',
                        'view_url' => 'http://localhost:8101/view/1',
                        'type' => 'image',
                        'size' => '728x90',
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

    public function fetchAllInventory(
        AccountId $sourceAddress,
        string $sourceHost,
        string $inventoryUrl
    ): CampaignCollection {
        return new CampaignCollection(...$this->campaigns);
    }

    public function fetchPaymentDetails(string $host, string $transactionId, int $limit, int $offset): array
    {
        static $arr;

        if ($arr === null) {
            $arr = [];
            for ($i = 0; $i < $limit; $i++) {
                $arr[] = [
                    'case_id' => Uuid::v4()->hex(),
                    'event_id' => Uuid::v4()->hex(),
                    'event_type' => 'view',
                    'banner_id' => 'b22e19a3874847f4a6287d26deacd208',
                    'zone_id' => 'a22e19a3874847f4a6287d26deacd208',
                    'publisher_id' => 'fa9611d2d2f74e3f89c0e18b7c401891',
                    'event_value' => 10,
                ];
                $arr[] = [
                    'case_id' => Uuid::v4()->hex(),
                    'event_id' => Uuid::v4()->hex(),
                    'event_type' => 'click',
                    'banner_id' => '9c6edfaef7454af4a96cb434c85323ee',
                    'zone_id' => '2c6edfaef7454af4a96cb434c85323ee',
                    'publisher_id' => 'd5f5deefd010449ab0ee0e5e6b884090',
                    'event_value' => 100,
                ];
            }
        } else {
            return array_chunk($arr, $limit, false)[(int)floor($offset / $limit)];
        }

        return $arr;
    }

    public function fetchInfo(UrlInterface $infoUrl): Info
    {
        $info = new Info(
            'adserver',
            'ADSERVER DEMAND',
            '0.1',
            new Url('https://server.example.com/'),
            new Url('https://panel.example.com/'),
            new Url('https://example.com/privacy'),
            new Url('https://example.com/terms'),
            new Url('https://inventory.example.com/import'),
            new AccountId('0001-00000004-DBEB'),
            new Email('mail@example.com'),
            ['PUB', 'ADV'],
            'public'
        );

        $info->setDemandFee(0.01);
        $info->setSupplyFee(0.01);
        $info->setStatistics(new InfoStatistics(7, 1, 0));

        return $info;
    }
}
