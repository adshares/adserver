<?php

/**
 * Copyright (c) 2018-2023 Adshares sp. z o.o.
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

use Adshares\Common\Domain\ValueObject\ChartResolution;
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

    public function fetchView(
        string $publisherId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchViewAll(
        string $publisherId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchViewInvalidRate(
        string $publisherId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchViewUnique(
        string $publisherId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchClick(
        string $publisherId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchClickAll(
        string $publisherId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchClickInvalidRate(
        string $publisherId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchRpc(
        string $publisherId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchRpm(
        string $publisherId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchSum(
        string $publisherId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchSumHour(
        string $publisherId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult;

    public function fetchCtr(
        string $publisherId,
        ChartResolution $resolution,
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
        ?array $publisherIds,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null,
        bool $showPublishers = false
    ): DataCollection;
}
