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

class PublisherReportResponse extends ReportResponse
{
    private const PUBLISHER_COLUMNS = [
        'Site Id',
        'Site Name',
        'Zone Id',
        'Zone Name',
        'Target Domain',
        'Revenue',
        'Clicks',
        'Views',
        'Ctr',
        'AverageRpc',
        'AverageRpm',
    ];

    protected function columns(): array
    {
        return self::PUBLISHER_COLUMNS;
    }

    protected function rows(): array
    {
        return array_map(
            static function ($item) {
                return [
                    $item['siteId'],
                    $item['siteName'],
                    $item['zoneId'],
                    $item['zoneName'],
                    $item['domain'] ?? '',
                    AdsConverter::clicksToAds($item['revenue']),
                    $item['clicks'],
                    $item['impressions'],
                    $item['ctr'],
                    AdsConverter::clicksToAds($item['averageRpc']),
                    AdsConverter::clicksToAds($item['averageRpm']),
                ];
            },
            $this->data
        );
    }
}
