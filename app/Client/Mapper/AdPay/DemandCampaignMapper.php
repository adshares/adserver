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
use Adshares\Adserver\Models\Campaign;
use DateTime;
use Illuminate\Database\Eloquent\Collection;
use stdClass;

class DemandCampaignMapper
{
    public static function mapCampaignCollectionToCampaignArray(Collection $campaigns): array
    {
        $campaignArray = $campaigns->map(
            function (Campaign $campaign) {
                $banners = [];
                $campaignArray = $campaign->toArray();

                $campaignBannersArray = $campaignArray['ads'];

                foreach ($campaignBannersArray as $banner) {
                    $banners[] = [
                        'banner_id' => $banner['uuid'],
                        'banner_size' => self::processSize($banner),
                        'keywords' => [
                            'type' => $banner['type'],
                        ],
                    ];
                }

                $targeting = self::processTargeting($campaignArray['targeting']);

                $timeStart = self::processDate($campaignArray['time_start']);
                $timeEnd = self::processDate($campaignArray['time_end']);

                $mapped = [
                    'campaign_id' => $campaignArray['uuid'],
                    'budget' => $campaignArray['basic_information']['budget'],
                    'max_cpc' => $campaignArray['basic_information']['max_cpc'],
                    'max_cpm' => $campaignArray['basic_information']['max_cpm'],
                    'time_start' => $timeStart,
                    'time_end' => $timeEnd,
                    'banners' => $banners,
                    'filters' => $targeting,
                    'keywords' => self::processKeywords($campaignArray),
                ];

                return $mapped;
            }
        )->toArray();

        return $campaignArray;
    }

    private static function processDate(?string $date): int
    {
        if ($date === null) {
            return (new DateTime())->modify('+1 year')->getTimestamp();
        }

        return DateTime::createFromFormat(DATE_ATOM, $date)->getTimestamp();
    }

    private static function processKeywords(array $campaign)
    {
        if ($campaign['classification_status'] != 2 || $campaign['classification_tags'] === null) {
            return new stdClass();
        }

        return array_fill_keys(explode(',', $campaign['classification_tags']), 1);
    }

    private static function processSize(array $banner): string
    {
        return $banner['creative_width'].'x'.$banner['creative_height'];
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
