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

class PublisherReportResponse extends ReportResponse
{
    private const PUBLISHER_COLUMNS = [
        'Domain',
        'Site Id',
        'Zone Id',
        'Clicks',
        'Impressions',
        'Ctr',
        'AverageRpc',
        'AverageRpm',
        'Revenue',
    ];

    protected function columns(): array
    {
       return self::PUBLISHER_COLUMNS;
    }

    protected function rows(): array
    {
        return array_map(static function($item) {
            return [
                $item['domain'] ?? 'None',
                $item['siteId'],
                $item['zoneId'] ?? 'None',
                $item['clicks'],
                $item['impressions'],
                $item['ctr'],
                $item['averageRpc'],
                $item['averageRpm'],
                $item['revenue'],
            ];
        }, $this->data);
    }
}
