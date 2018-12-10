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

namespace Adshares\Adserver\Tests\Client\Mapper\AdSelect;

use Adshares\Adserver\Client\Mapper\AdSelect\CampaignMapper;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Supply\Domain\Factory\CampaignFactory;
use DateTime;
use PHPUnit\Framework\TestCase;
use stdClass;

final class CampaignMapperTest extends TestCase
{
    private $campaignData;

    public function __construct(
        ?string $name = null,
        array $data = [],
        string $dataName = ''
    ) {
        $this->campaignData = [
            'id' => 1,
            'uuid' => Uuid::v4(),
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
                    'uuid' => (string)Uuid::v4(),
                    'serve_url' => 'http://localhost:8101/serve/1',
                    'click_url' => 'http://localhost:8101/click/1',
                    'view_url' => 'http://localhost:8101/view/1',
                    'type' => 'image',
                    'width' => 728,
                    'height' => 90,
                ],
                [
                    'uuid' => (string)Uuid::v4(),
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
            'demand_host' => 'localhost:8101',
            'targeting_excludes' => [],
            'targeting_requires' => [],
        ];

        parent::__construct($name, $data, $dataName);
    }

    public function testMappignCampaign(): void
    {
        $expected = [
            'campaign_id' => (string)$this->campaignData['uuid'],
            'time_start' => $this->campaignData['date_start']->getTimestamp(),
            'time_end' => $this->campaignData['date_end']->getTimestamp(),
            'banners' => [
                [
                    'banner_id' => $this->campaignData['banners'][0]['uuid'],
                    'banner_size' => '728x90',
                    'keywords' => [
                        'type' => 'image',
                    ],
                ],
                [
                    'banner_id' => $this->campaignData['banners'][1]['uuid'],
                    'banner_size' => '728x90',
                    'keywords' => [
                        'type' => 'image',
                    ],
                ],
            ],
            'keywords' => [
                'source_host' => $this->campaignData['source_campaign']['host'],
                'adshares_address' => $this->campaignData['source_campaign']['address'],
            ],
            'filters' => [
                'requires' => new stdClass(),
                'excludes' => new stdClass(),
            ],
        ];

        $campaign = CampaignFactory::createFromArray($this->campaignData);
        $this->assertEquals($expected, CampaignMapper::map($campaign)[0]);
    }
}
