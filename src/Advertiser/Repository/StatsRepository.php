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

namespace Adshares\Advertiser\Repository;

use Adshares\Advertiser\Dto\ChartResult;
use Adshares\Advertiser\Dto\StatsEntryValues;
use Adshares\Advertiser\Dto\StatsResult;
use DateTime;

interface StatsRepository
{
    public const VIEW_TYPE = 'view';
    public const CLICK_TYPE = 'click';
    public const CPC_TYPE = 'cpc';
    public const CPM_TYPE = 'cpm';
    public const SUM_TYPE = 'sum';
    public const CTR_TYPE = 'ctr';
    public const STATS_TYPE = 'stats';
    public const STATS_SUM_TYPE = 'statsSum';

    public const HOUR_RESOLUTION = 'hour';
    public const DAY_RESOLUTION = 'day';
    public const WEEK_RESOLUTION = 'week';
    public const MONTH_RESOLUTION = 'month';
    public const QUARTER_RESOLUTION = 'quarter';
    public const YEAR_RESOLUTION = 'year';

    public function fetchView(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult;

    public function fetchClick(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult;

    public function fetchCpc(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult;

    public function fetchCpm(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult;

    public function fetchSum(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult;

    public function fetchCtr(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult;

    public function fetchStats(
        string $advertiserId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): StatsResult;

    public function fetchStatsTotal(
        string $advertiserId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): StatsEntryValues;
}
