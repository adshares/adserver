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

declare(strict_types=1);

namespace Adshares\Adserver\Client\Mapper\AdPay;

use Adshares\Adserver\Client\Mapper\AdSelect\TargetingMapper;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\ConversionDefinition;
use DateTime;
use DateTimeInterface;
use Illuminate\Support\Collection;

class DemandCampaignMapper
{
    public static function mapCampaignCollectionToCampaignArray(Collection $campaigns): array
    {
        return $campaigns->map(
            function (Campaign $campaign) {
                $basicInformation = $campaign->basic_information;

                return [
                    'id' => $campaign->uuid,
                    'advertiser_id' => Campaign::fetchAdvertiserId($campaign->id),
                    'budget' => $basicInformation['budget'],
                    'max_cpc' => $basicInformation['max_cpc'],
                    'max_cpm' => $basicInformation['max_cpm'],
                    'time_start' => DateTime::createFromFormat(
                        DateTimeInterface::ATOM,
                        $campaign->time_start
                    )->getTimestamp(),
                    'time_end' => null !== $campaign->time_end ? DateTime::createFromFormat(
                        DateTimeInterface::ATOM,
                        $campaign->time_end
                    )->getTimestamp() : null,
                    'banners' => self::extractBanners($campaign),
                    'conversions' => self::processConversions($campaign->conversions),
                    'filters' => self::processTargeting($campaign->targeting),
                    'bid_strategy_id' => $campaign->bid_strategy_uuid,
                ];
            }
        )->toArray();
    }

    private static function extractBanners(Campaign $campaign): array
    {
        $banners = [];

        /** @var Banner $ad */
        foreach ($campaign->ads as $ad) {
            $banners[] = [
                'id' => $ad->uuid,
                'size' => $ad->creative_size,
                'type' => $ad->creative_type,
            ];
        }

        return $banners;
    }

    private static function processConversions(Collection $conversions): array
    {
        $mapped = $conversions->map(
            function (ConversionDefinition $conversion) {
                return $conversion->only(
                    [
                        'uuid',
                        'limit_type',
                        'is_repeatable',
                    ]
                );
            }
        )->toArray();

        foreach ($mapped as &$item) {
            $item['id'] = $item['uuid'];
            unset($item['uuid']);
        }

        return $mapped;
    }

    private static function processTargeting(array $targeting): array
    {
        return TargetingMapper::map(
            $targeting['requires'] ?? [],
            $targeting['excludes'] ?? []
        );
    }

    public static function mapCampaignCollectionToCampaignIds(Collection $campaigns): array
    {
        $campaignIds = [];
        foreach ($campaigns as $campaign) {
            $campaignIds[] = $campaign->uuid;
        }

        return $campaignIds;
    }
}
