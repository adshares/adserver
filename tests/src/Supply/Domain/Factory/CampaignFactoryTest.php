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

namespace Adshares\Tests\Supply\Domain\Factory;

use Adshares\Common\Domain\ValueObject\Uuid;
use Adshares\Supply\Domain\Factory\CampaignFactory;
use Adshares\Supply\Domain\Factory\Exception\InvalidCampaignArgumentException;
use DateTime;
use PHPUnit\Framework\TestCase;

final class CampaignFactoryTest extends TestCase
{
    private array $data;

    protected function setUp(): void
    {
        $this->data = [
            'id' => Uuid::v4(),
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
                self::bannerModel(),
                self::bannerVideo(),
            ],
            'max_cpc' => 100000000000,
            'max_cpm' => 100000000000,
            'budget' => 1000000000000,
            'demand_host' => 'localhost:8101',
            'medium' => 'web',
            'vendor' => null,
            'targeting_excludes' => [],
            'targeting_requires' => [],
        ];
    }

    private static function banner(): array
    {
        $uuid = Uuid::v4();

        return [
            'demand_banner_id' => $uuid,
            'serve_url' => 'http://localhost:8101/serve/x' . $uuid . '.doc',
            'click_url' => 'http://localhost:8101/click/' . $uuid,
            'view_url' => 'http://localhost:8101/view/' . $uuid,
            'type' => 'image',
            'mime' => 'image/png',
            'size' => '728x90',
        ];
    }

    private static function bannerVideo(): array
    {
        return array_merge(
            self::banner(),
            [
                'type' => 'video',
                'mime' => 'video/mp4',
                'size' => '320x240',
                'classification' => [
                    'keywords' => [
                        'category' => [
                            'safe',
                        ],
                        'classified' => [
                            '1',
                        ],
                    ],
                ],
            ]
        );
    }

    private static function bannerModel(): array
    {
        return array_merge(
            self::banner(),
            [
                'type' => 'model',
                'mime' => 'model/gltf-binary',
                'size' => 'cube',
            ]
        );
    }

    public function testCreateFromArrayWhenInvalid(): void
    {
        $this->expectException(InvalidCampaignArgumentException::class);

        $data = $this->data;
        unset($data['budget']);

        CampaignFactory::createFromArray($data);
    }

    public function testCreateFromArrayWhenNestedItemIsInvalid(): void
    {
        $this->expectException(InvalidCampaignArgumentException::class);

        $data = $this->data;
        unset($data['source_campaign']['host'], $data['source_campaign']['version']);

        CampaignFactory::createFromArray($data);
    }

    public function testCreateFromArrayWhenBannerIdMissing(): void
    {
        $this->expectException(InvalidCampaignArgumentException::class);

        $data = $this->data;
        unset($data['banners'][0]['demand_banner_id']);
        unset($data['banners'][0]['id']);

        CampaignFactory::createFromArray($data);
    }

    public function testCreateFromArrayWhenBannerTypeMissing(): void
    {
        $this->expectException(InvalidCampaignArgumentException::class);

        $data = $this->data;
        unset($data['banners'][0]['type']);

        CampaignFactory::createFromArray($data);
    }

    public function testCreateFromArrayWhenBannerTypeInvalid(): void
    {
        $this->expectException(InvalidCampaignArgumentException::class);

        $data = $this->data;
        $data['banners'][0]['type'] = 'invalid';

        CampaignFactory::createFromArray($data);
    }

    public function testCreateFromArrayWhenVideoBannerSizeFormatInvalid(): void
    {
        $this->expectException(InvalidCampaignArgumentException::class);

        $data = $this->data;
        $data['banners'][2]['size'] = 10;

        CampaignFactory::createFromArray($data);
    }

    public function testCreateFromArrayWhenAllRequiredFieldsAreFilled(): void
    {
        $instance = CampaignFactory::createFromArray($this->data);

        $this->assertEquals($this->data['id'], $instance->getId());
    }
}
