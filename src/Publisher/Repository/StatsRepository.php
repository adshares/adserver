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

namespace Adshares\Publisher\Repository;

use Adshares\Publisher\Dto\Result\ChartResult;
use Adshares\Publisher\Dto\Result\Stats\DataCollection;
use Adshares\Publisher\Dto\Result\Stats\Total;
use DateTime;

interface StatsRepository
{
    public const TYPE_VIEW = 'view';
    public const TYPE_VIEW_ALL = 'viewAll';
    public const TYPE_VIEW_INVALID_RATE = 'viewInvalidRate';
    public const TYPE_VIEW_UNIQUE = 'viewUnique';
    public const TYPE_CLICK = 'click';
    public const TYPE_CLICK_ALL = 'clickAll';
    public const TYPE_CLICK_INVALID_RATE = 'clickInvalidRate';
    public const TYPE_RPC = 'rpc';
    public const TYPE_RPM = 'rpm';
    public const TYPE_REVENUE_BY_CASE = 'sum';
    public const TYPE_REVENUE_BY_HOUR = 'sumHour';
    public const TYPE_CTR = 'ctr';
    public const TYPE_STATS = 'stats';
    public const TYPE_STATS_REPORT = 'statsReport';

    public const RESOLUTION_HOUR = 'hour';
    public const RESOLUTION_DAY = 'day';
    public const RESOLUTION_WEEK = 'week';
    public const RESOLUTION_MONTH = 'month';
    public const RESOLUTION_QUARTER = 'quarter';
    public const RESOLUTION_YEAR = 'year';

    public function fetchView(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchViewAll(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchViewInvalidRate(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchViewUnique(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchClick(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchClickAll(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchClickInvalidRate(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchRpc(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchRpm(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchSum(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchSumHour(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchCtr(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchStats(
        ?string $publisherId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): DataCollection;

    public function fetchStatsTotal(
        ?string $publisherId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): Total;

    public function fetchStatsToReport(
        ?string $publisherId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): DataCollection;
}
