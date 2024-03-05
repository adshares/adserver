<?php

/**
 * Copyright (c) 2018-2024 Adshares sp. z o.o.
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
use Adshares\Config\AppMode;
use Adshares\Config\RegistrationMode;
use Adshares\Supply\Application\Dto\Info;
use Adshares\Supply\Application\Dto\InfoStatistics;
use Adshares\Supply\Application\Service\DemandClient;
use Adshares\Supply\Application\Service\Exception\EmptyInventoryException;
use Adshares\Supply\Domain\Factory\CampaignFactory;
use Adshares\Supply\Domain\Model\CampaignCollection;
use DateTime;
use DateTimeImmutable;

class DummyDemandClient implements DemandClient
{
    public array $campaigns;
    private static ?array $creditDetails = null;
    private static ?array $paymentDetails = null;

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
                    'address' => '0001-00000001-8B4E',
                    'version' => '0.1',
                    'created_at' => new DateTime(),
                    'updated_at' => new DateTime(),
                ],
                'banners' => [
                    self::banner(),
                    self::banner(),
                    self::banner(),
                ],
                'max_cpc' => 100000000000,
                'max_cpm' => 100000000000,
                'budget' => 1000000000000,
                'medium' => 'web',
                'vendor' => null,
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
                    'address' => '0001-00000001-8B4E',
                    'version' => '0.1',
                    'created_at' => (new DateTime()),
                    'updated_at' => (new DateTime()),
                ],
                'banners' => [
                    self::banner(),
                    self::banner(),
                    self::banner(),
                ],
                'max_cpc' => 1000000000000,
                'max_cpm' => 1000000000000,
                'budget' => 1000000000000000,
                'medium' => 'web',
                'vendor' => null,
                'demand_host' => 'localhost:8101',
                'targeting_excludes' => [],
                'targeting_requires' => [],
            ]),
        ];
    }

    private static function banner(): array
    {
        $uuid = Uuid::v4();

        return  [
            'demand_banner_id' => $uuid,
            'serve_url' => 'http://localhost:8101/serve/x' . $uuid . '.doc',
            'click_url' => 'http://localhost:8101/click/' . $uuid,
            'view_url' => 'http://localhost:8101/view/' . $uuid,
            'type' => 'image',
            'mime' => 'image/png',
            'size' => '728x90',
            'signed_at' => new DateTimeImmutable('-1 hour'),
        ];
    }

    public function fetchAllInventory(
        AccountId $sourceAddress,
        string $sourceHost,
        string $inventoryUrl,
        bool $isAdsTxtRequiredBySourceHost,
    ): CampaignCollection {
        return new CampaignCollection(...$this->campaigns);
    }

    public function fetchPaymentDetailsMeta(string $host, string $transactionId): array
    {
        return [
            'credits' => (null === self::$creditDetails) ? [
                'count' => 0,
                'sum' => 0,
            ] : [
                'count' => count(self::$creditDetails),
                'sum' => array_reduce(self::$creditDetails, fn($carry, $item) => $carry + $item['value'], 0),
            ],
            'events' => (null === self::$paymentDetails) ? [
                'count' => 0,
                'sum' => 0,
            ] : [
                'count' => count(self::$paymentDetails),
                'sum' => array_reduce(self::$paymentDetails, fn($carry, $item) => $carry + $item['event_value'], 0),
            ],
        ];
    }

    public function fetchPaymentDetails(string $host, string $transactionId, int $limit, int $offset): array
    {
        if (self::$paymentDetails === null) {
            self::$paymentDetails = [];
            for ($i = 0; $i < $limit; $i++) {
                self::$paymentDetails[] = [
                    'case_id' => Uuid::v4()->hex(),
                    'event_id' => Uuid::v4()->hex(),
                    'event_type' => 'view',
                    'banner_id' => 'b22e19a3874847f4a6287d26deacd208',
                    'zone_id' => 'a22e19a3874847f4a6287d26deacd208',
                    'publisher_id' => 'fa9611d2d2f74e3f89c0e18b7c401891',
                    'event_value' => 1000,
                ];
                self::$paymentDetails[] = [
                    'case_id' => Uuid::v4()->hex(),
                    'event_id' => Uuid::v4()->hex(),
                    'event_type' => 'click',
                    'banner_id' => '9c6edfaef7454af4a96cb434c85323ee',
                    'zone_id' => '2c6edfaef7454af4a96cb434c85323ee',
                    'publisher_id' => 'd5f5deefd010449ab0ee0e5e6b884090',
                    'event_value' => 10000,
                ];
            }
        } else {
            if ($offset >= count(self::$paymentDetails)) {
                throw new EmptyInventoryException('Empty list');
            }
            return array_chunk(self::$paymentDetails, $limit)[(int)floor($offset / $limit)];
        }

        return self::$paymentDetails;
    }

    public function fetchCreditDetails(string $host, string $transactionId, int $limit, int $offset): array
    {
        if (self::$creditDetails === null) {
            self::$creditDetails = [];
            for ($i = 0; $i < $limit; $i++) {
                self::$creditDetails[] = [
                    'campaign_id' => Uuid::v4()->hex(),
                    'value' => 20_000,
                ];
            }
        } else {
            if ($offset >= count(self::$creditDetails)) {
                throw new EmptyInventoryException('Empty list');
            }
            return array_chunk(self::$creditDetails, $limit)[(int)floor($offset / $limit)];
        }
        return self::$creditDetails;
    }

    public function fetchInfo(UrlInterface $infoUrl): Info
    {
        $info = new Info(
            'adserver',
            'ADSERVER DEMAND',
            '0.1',
            new Url('https://server.example.com/'),
            new Url('https://panel.example.com/'),
            new Url('https://example.com/'),
            new Url('https://example.com/privacy'),
            new Url('https://example.com/terms'),
            new Url('https://inventory.example.com/import'),
            new AccountId('0001-00000004-DBEB'),
            new Email('mail@example.com'),
            [Info::CAPABILITY_PUBLISHER, Info::CAPABILITY_ADVERTISER],
            RegistrationMode::PUBLIC,
            AppMode::OPERATIONAL,
            'example.com',
            false,
            0,
        );

        $info->setDemandFee(0.01);
        $info->setSupplyFee(0.01);
        $info->setStatistics(new InfoStatistics(7, 1, 0));

        return $info;
    }
}
