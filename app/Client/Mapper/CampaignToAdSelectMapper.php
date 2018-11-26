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

namespace Adshares\Adserver\Client\Mapper;

use Adshares\Supply\Domain\Model\Banner;
use Adshares\Supply\Domain\Model\Campaign;

class CampaignToAdSelectMapper
{
    public static function map(Campaign $campaign): array
    {
        $banners = [];
        $campaignArray = $campaign->toArray();

        /** @var Banner $banner */
        foreach ($campaign->getBanners() as $banner) {
            $banners[] = [
                'banner_id' => $banner->getId(),
                'banner_size' => $banner->getSize(),
                'campaign_id' => $campaignArray['demand_campaign_id'],
                'keywords' => [
                    'type' => $banner->getType(),
                ],
            ];
        }

        $targeting = TargetingToAdSelectMapper::map(
            $campaignArray['targeting_requires'],
            $campaignArray['targeting_excludes']
        );

        $mapped = [
            'campaign_id' => $campaignArray['demand_campaign_id'],
            'time_start' => (int)$campaignArray['date_start']->format('U'),
            'time_end' => (int)$campaignArray['date_end']->format('U'),
            'banners' => $banners,
            'keywords' => [
                'source_host' => $campaignArray['source_host'],
                'adshares_address' => $campaignArray['source_address'],
            ],
        ];

        if ($targeting) {
            $mapped['filters'] = $targeting;
        }

        return [$mapped];
    }
}
