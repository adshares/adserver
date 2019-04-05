<?php
/**
 * Copyright (c) 2018-2019 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Http\Response\Stats;

use Adshares\Ads\Util\AdsConverter;
use function array_map;

class AdvertiserReportResponse extends ReportResponse
{
    private const ADVERTISER_COLUMNS = [
        'Campaign Id',
        'Campaign Name',
        'Banner Id',
        'Banner Name',
        'Target Domain',
        'Cost',
        'Clicks',
        'Views',
        'Ctr',
        'AverageCpc',
        'AverageCpm',
    ];

    protected function columns(): array
    {
        return self::ADVERTISER_COLUMNS;
    }

    protected function rows(): array
    {
        return array_map(
            static function ($item) {
                return [
                    $item['campaignId'],
                    $item['campaignName'],
                    $item['bannerId'],
                    $item['bannerName'],
                    $item['domain'] ?? '',
                    AdsConverter::clicksToAds($item['cost']),
                    $item['clicks'],
                    $item['impressions'],
                    $item['ctr'],
                    AdsConverter::clicksToAds($item['averageCpc']),
                    AdsConverter::clicksToAds($item['averageCpm']),
                ];
            },
            $this->data
        );
    }
}
