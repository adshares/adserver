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

namespace Adshares\Adserver\Tests\Client\Mapper\AdPay;

use Adshares\Adserver\Client\Mapper\AdPay\DemandCampaignMapper;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\ConversionDefinition;
use Adshares\Adserver\Models\User;
use Adshares\Adserver\Tests\TestCase;
use DateTime;
use Illuminate\Support\Collection;

final class DemandCampaignMapperTest extends TestCase
{
    public function testMappingIds(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Campaign $campaign */
        $campaign = Campaign::factory()->create(['user_id' => $user->id, 'status' => Campaign::STATUS_INACTIVE]);
        $expected = [$campaign->uuid];
        $collection = new Collection([$campaign]);

        self::assertEquals($expected, DemandCampaignMapper::mapCampaignCollectionToCampaignIds($collection));
    }

    public function testMappingToArray(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        /** @var Campaign $campaign */
        $dateTimeStart = new DateTime();
        $dateTimeEnd = new DateTime('+1 hour');
        $campaign = Campaign::factory()->create(
            [
                'uuid' => '0123456789ABCDEF0123456789ABCDEF',
                'user_id' => $user->id,
                'medium' => 'metaverse',
                'vendor' => 'my-metaverse',
                'status' => Campaign::STATUS_INACTIVE,
                'landing_url' => 'https://example.com',
                'time_start' => $dateTimeStart->format(DATE_ATOM),
                'time_end' => $dateTimeEnd->format(DATE_ATOM),
                'name' => 'test campaign',
                'max_cpc' => 0,
                'max_cpm' => null,
                'budget' => 10000000000000,
                'targeting_excludes' => [
                    'site' => [
                        'quality' => ['low'],
                        'category' => ['adult', 'diets',],
                    ],
                ],
                'targeting_requires' => [
                    'user' => [
                        'country' => ['us'],
                    ],
                ],
                'bid_strategy_uuid' => '00000000000000000000000000000000',
            ]
        );
        /** @var Banner $banner */
        $banner = Banner::factory()->create(
            [
                'campaign_id' => $campaign->id,
                'creative_contents' => '0123456789012345678901234567890123456789',
                'creative_type' => 'image',
                'creative_mime' => 'image/png',
                'creative_size' => '300x250',
                'name' => 'IMAGE 1',
                'status' => Banner::STATUS_INACTIVE,
            ]
        );
        /** @var ConversionDefinition $conversionDefinition */
        $conversionDefinition = Conversiondefinition::factory()->create(
            [
                'campaign_id' => $campaign->id,
                'limit_type' => 'in_budget',
                'is_repeatable' => true,
            ]
        );
        $collection = new Collection([$campaign]);

        $expected = [
            [
                'id' => $campaign->uuid,
                'advertiser_id' => $user->uuid,
                'medium' => 'metaverse',
                'vendor' => 'my-metaverse',
                'budget' => 10000000000000,
                'max_cpc' => 0,
                'max_cpm' => null,
                'time_start' => $dateTimeStart->getTimestamp(),
                'time_end' => $dateTimeEnd->getTimestamp(),
                'banners' => [
                    [
                        'id' => $banner->uuid,
                        'size' => '300x250',
                        'type' => 'image',
                    ],
                ],
                'conversions' => [
                    [
                        'id' => $conversionDefinition->uuid,
                        'limit_type' => 'in_budget',
                        'is_repeatable' => true,
                    ]
                ],
                'filters' => [
                    'exclude' => [
                        'site:quality' => ['low'],
                        'site:category' => ['adult', 'diets'],
                    ],
                    'require' => [
                        'user:country' => ['us'],
                    ],
                ],
                'bid_strategy_id' => '00000000000000000000000000000000',
            ]
        ];

        self::assertEquals($expected, DemandCampaignMapper::mapCampaignCollectionToCampaignArray($collection));
    }
}
