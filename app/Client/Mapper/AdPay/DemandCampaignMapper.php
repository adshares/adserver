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

namespace Adshares\Adserver\Client\Mapper\AdPay;

use Adshares\Adserver\Client\Mapper\AdSelect\TargetingMapper;
use Adshares\Adserver\Models\Banner;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\ConversionDefinition;
use DateTime;
use Illuminate\Support\Collection;
use stdClass;

class DemandCampaignMapper
{
    public static function mapCampaignCollectionToCampaignArray(Collection $campaigns): array
    {
        return $campaigns->map(
            function (Campaign $campaign) {
                $campaignArray = $campaign->toArray();

                return [
                    'campaign_id' => $campaign->uuid,
                    'advertiser_id' => Campaign::fetchAdvertiserId($campaign->id),
                    'budget' => $campaignArray['basic_information']['budget'],
                    'max_cpc' => $campaignArray['basic_information']['max_cpc'],
                    'max_cpm' => $campaignArray['basic_information']['max_cpm'],
                    'time_start' => self::processDate($campaign->time_start),
                    'time_end' => self::processDate($campaign->time_end),
                    'banners' => self::extractAds($campaign),
                    'conversion_definitions' => self::processConversions($campaign->conversions),
                    'filters' => self::processTargeting($campaignArray['targeting']),
                    'keywords' => self::processKeywords($campaignArray),
                ];
            }
        )->toArray();
    }

    private static function processDate(?string $date): int
    {
        if ($date === null) {
            return (new DateTime())->modify('+1 year')->getTimestamp();
        }

        return DateTime::createFromFormat(DateTime::ATOM, $date)->getTimestamp();
    }

    private static function processKeywords(array $campaign)
    {
        if ($campaign['classification_status'] != 2 || $campaign['classification_tags'] === null) {
            return new stdClass();
        }

        return array_fill_keys(explode(',', $campaign['classification_tags']), 1);
    }

    private static function extractAds(Campaign $campaign): array
    {
        $banners = [];

        /** @var Banner $ad */
        foreach ($campaign->ads as $ad) {
            $banners[] = [
                'banner_id' => $ad->uuid,
                'banner_size' => $ad->getFormattedSize(),
                'keywords' => [
                    'type' => [$ad->type],
                ],
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
                        'value',
                        'is_value_mutable',
                        'limit',
                        'is_repeatable',
                        'cost',
                    ]
                );
            }
        )->toArray();

        foreach ($mapped as &$item) {
            $item['conversion_id'] = $item['uuid'];
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
