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

namespace Adshares\Adserver\Tests\Client\Mapper\AdSelect;

use Adshares\Adserver\Client\Mapper\AdSelect\CampaignMapper;
use Adshares\Adserver\Tests\TestCase;
use Adshares\Common\Application\Dto\TaxonomyV2\Medium;
use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Mock\Repository\DummyConfigurationRepository;
use Adshares\Supply\Domain\Factory\CampaignFactory;
use DateTime;
use stdClass;

final class CampaignMapperTest extends TestCase
{
    public function testMappingCampaign(): void
    {
        $campaignData = $this->getCampaignData();

        $expected = [
            'campaign_id' => (string)$campaignData['id'],
            'time_start' => $campaignData['date_start']->getTimestamp(),
            'time_end' => $campaignData['date_end']->getTimestamp(),
            'banners' => [
                [
                    'banner_id' => (string)$campaignData['banners'][0]['id'],
                    'banner_size' => '728x90',
                    'keywords' => [
                        'type' => ['image'],
                        'mime' => ['image/png'],
                    ],
                ],
                [
                    'banner_id' => (string)$campaignData['banners'][1]['id'],
                    'banner_size' => '728x90',
                    'keywords' => [
                        'type' => ['image'],
                    ],
                ],
            ],
            'keywords' => [
                'source_host' => $campaignData['source_campaign']['host'],
                'adshares_address' => $campaignData['source_campaign']['address'],
            ],
            'filters' => [
                'require' => new stdClass(),
                'exclude' => new stdClass(),
            ],
            'max_cpc' => 100000000001,
            'max_cpm' => 100000000002,
            'budget' => 1000000000000,
        ];

        $campaign = CampaignFactory::createFromArray($campaignData);

        $this->assertEquals($expected, CampaignMapper::map($this->getMedium(), $campaign));
    }

    public function testMappingCampaignWithClassification(): void
    {
        $campaignDataWithClassification = $this->getCampaignDataWithClassification();

        $expected = [
            'campaign_id' => (string)$campaignDataWithClassification['id'],
            'time_start' => $campaignDataWithClassification['date_start']->getTimestamp(),
            'banners' => [
                [
                    'banner_id' => (string)$campaignDataWithClassification['banners'][0]['id'],
                    'banner_size' => '728x90',
                    'keywords' => [
                        'type' => ['image'],
                        'mime' => ['image/png'],
                        'test_classifier:category' => [
                            'crypto',
                            'gambling',
                        ],
                        'test_classifier:classified' => ['1'],
                    ],
                ],
            ],
            'keywords' => [
                'source_host' => $campaignDataWithClassification['source_campaign']['host'],
                'adshares_address' => $campaignDataWithClassification['source_campaign']['address'],
            ],
            'filters' => [
                'require' => [
                    'device:type' => ['desktop'],
                ],
                'exclude' => new stdClass(),
            ],
            'max_cpc' => 10000000001,
            'max_cpm' => 10000000002,
            'budget' => 93555000000,
        ];

        $campaign = CampaignFactory::createFromArray($campaignDataWithClassification);
        $mapped = CampaignMapper::map($this->getMedium(), $campaign);
        // time_end must be compared separately with timestamp range because it is overwritten
        $mappedTimeEnd = $mapped['time_end'];
        unset($mapped['time_end']);

        $this->assertEquals($expected, $mapped);
        $this->assertNotNull($mappedTimeEnd);
        $this->assertGreaterThan((new DateTime('+11 months'))->getTimestamp(), $mappedTimeEnd);
        $this->assertLessThanOrEqual((new DateTime('+1 year'))->getTimestamp(), $mappedTimeEnd);
    }

    public function testMappingCampaignWithBannerVideo(): void
    {
        $campaignData = array_merge(
            $this->getCampaignData(),
            [
                'banners' => [
                    self::getBannerData(
                        [
                            'type' => 'video',
                            'mime' => 'video/mp4',
                            'size' => '300x250',
                        ]
                    ),
                ],
            ]
        );
        $campaign = CampaignFactory::createFromArray($campaignData);
        $mapped = CampaignMapper::map($this->getMedium(), $campaign);

        $bannerSizes = $mapped['banners'][0]['banner_size'];
        self::assertIsArray($bannerSizes);
        self::assertContains('300x250', $bannerSizes);
    }

    private function getCampaignData(): array
    {
        return [
            'id' => Uuid::v4(),
            'demand_id' => Uuid::v4(),
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
                self::getBannerData(),
                self::getBannerData(['mime' => null]),
            ],
            'max_cpc' => 100000000001,
            'max_cpm' => 100000000002,
            'budget' => 1000000000000,
            'demand_host' => 'localhost:8101',
            'medium' => 'web',
            'vendor' => null,
            'targeting_excludes' => [],
            'targeting_requires' => [],
        ];
    }

    private static function getBannerData(array $arr = []): array
    {
        $uuid = Uuid::v4();

        return array_merge(
            [
                'id' => Uuid::v4(),
                'demand_banner_id' => $uuid,
                'serve_url' => 'http://localhost:8101/serve/x' . $uuid . '.doc',
                'click_url' => 'http://localhost:8101/click/' . $uuid,
                'view_url' => 'http://localhost:8101/view/' . $uuid,
                'type' => 'image',
                'mime' => 'image/png',
                'size' => '728x90',
            ],
            $arr
        );
    }

    private function getCampaignDataWithClassification(): array
    {
        return [
            'id' => Uuid::v4(),
            'demand_id' => Uuid::v4(),
            'landing_url' => 'http://adshares.pl',
            'date_start' => (new DateTime())->modify('-1 day'),
            'date_end' => null,
            'created_at' => (new DateTime())->modify('-2 days'),
            'updated_at' => (new DateTime())->modify('-2 days'),
            'source_campaign' => [
                'host' => 'localhost:8101',
                'address' => '0001-00000001-8B4E',
                'version' => '0.1',
                'created_at' => new DateTime(),
                'updated_at' => new DateTime(),
            ],
            'banners' => [
                [
                    'id' => Uuid::v4(),
                    'demand_banner_id' => Uuid::v4(),
                    'serve_url' => 'http://localhost:8101/serve/1',
                    'click_url' => 'http://localhost:8101/click/1',
                    'view_url' => 'http://localhost:8101/view/1',
                    'type' => 'image',
                    'mime' => 'image/png',
                    'checksum' => 'feca8167499895B0c30bbbc3c668550161f64235',
                    'size' => '728x90',
                    'classification' => [
                        'test_classifier' => [
                            'category' => [
                                'crypto',
                                'gambling',
                            ],
                            'classified' => ['1'],
                        ],
                    ],
                ],
            ],
            'max_cpc' => 10000000001,
            'max_cpm' => 10000000002,
            'budget' => 93555000000,
            'medium' => 'web',
            'vendor' => null,
            'targeting_excludes' => [],
            'targeting_requires' => [
                "device" => [
                    "type" => [
                        "desktop",
                    ],
                ],
            ],
        ];
    }

    private function getMedium(): Medium
    {
        return (new DummyConfigurationRepository())->fetchMedium();
    }
}
