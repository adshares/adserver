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

namespace Adshares\Adserver\Http\Response\Stats;

use Adshares\Ads\Util\AdsConverter;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

use function array_map;

class AdvertiserReportResponse extends ReportResponse
{
    private const ADVERTISER_COLUMNS = [
        'Campaign' => [
            'width' => 24,
        ],
        'Banner' => [
            'width' => 24,
        ],
        'Domain' => [
            'width' => 24,
        ],
        'Cost' => [
            'format' => NumberFormat::FORMAT_CURRENCY_USD_SIMPLE,
            'fill' => 'E5F2FF',
            'color' => '003771',
        ],
        'Total views' => [
            'format' => '#,##0',
        ],
        'Views' => [
            'format' => '#,##0',
            'comment' => 'Total views excluding rejected ones.',
            'fill' => 'B8F4B5',
            'color' => '056100',
        ],
        'IVR' => [
            'format' => NumberFormat::FORMAT_PERCENTAGE_00,
            'comment' => 'Invalid views rate (IVR) is the ratio of rejected views to the number of total views.',
        ],
        'Unique views' => [
            'format' => '#,##0',
            'comment' => 'Unique views are the number of unique users that views your campaign.',
        ],
        'CPM' => [
            'format' => '"$"#,##0.0000_-',
            'comment' => 'Average cost-per-thousand views (CPM).',
        ],
        'Total clicks' => [
            'format' => '#,##0',
        ],
        'Clicks' => [
            'format' => '#,##0',
            'comment' => 'Total clicks excluding rejected ones.',
            'fill' => 'B8F4B5',
            'color' => '056100',
        ],
        'ICR' => [
            'format' => NumberFormat::FORMAT_PERCENTAGE_00,
            'comment' => 'Invalid clicks rate (ICR) is the ratio of rejected clicks to the number of total clicks.',
        ],
        'CTR' => [
            'format' => NumberFormat::FORMAT_PERCENTAGE_00,
            'comment' => 'Click-through rate (CTR) is the ratio of users who clicked on your ad to the number of ' .
                'total users who viewed it.',
        ],
        'CPC' => [
            'format' => '"$"#,##0.0000_-',
            'comment' => 'Average cost-per-click (CPC).',
        ],
    ];

    /** @var bool */
    private $isAdmin;

    public function __construct(array $data, ?string $name = null, ?string $creator = null, ?bool $isAdmin = false)
    {
        $this->isAdmin = $isAdmin;

        parent::__construct($data, $name, $creator);
    }

    protected function columns(): array
    {
        if ($this->isAdmin) {
            return array_merge(['User' => []], self::ADVERTISER_COLUMNS);
        }

        return self::ADVERTISER_COLUMNS;
    }

    protected function rows(): array
    {
        $isAdminReport = $this->isAdmin;

        return array_map(
            static function ($item) use ($isAdminReport) {
                $row = [
                    $item['campaignName'],
                    $item['bannerName'],
                    $item['domain'] ?? '',
                    AdsConverter::clicksToAds($item['cost']),
                    $item['impressionsAll'],
                    $item['impressions'],
                    $item['impressionsInvalidRate'],
                    $item['impressionsUnique'],
                    AdsConverter::clicksToAds($item['averageCpm']),
                    $item['clicksAll'],
                    $item['clicks'],
                    $item['clicksInvalidRate'],
                    $item['ctr'],
                    AdsConverter::clicksToAds($item['averageCpc']),
                ];

                if ($isAdminReport) {
                    array_unshift($row, $item['advertiser'] ?? '');
                }

                return $row;
            },
            $this->data
        );
    }
}
