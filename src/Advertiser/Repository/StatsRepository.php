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

namespace Adshares\Advertiser\Repository;

use Adshares\Adserver\Http\Requests\Filter\FilterCollection;
use Adshares\Advertiser\Dto\Result\ChartResult;
use Adshares\Advertiser\Dto\Result\Stats\ConversionDataCollection;
use Adshares\Advertiser\Dto\Result\Stats\DataCollection;
use Adshares\Advertiser\Dto\Result\Stats\Total;
use Adshares\Common\Domain\ValueObject\ChartResolution;
use DateTime;
use DateTimeInterface;

interface StatsRepository
{
    public const TYPE_VIEW = 'view';
    public const TYPE_VIEW_ALL = 'viewAll';
    public const TYPE_VIEW_INVALID_RATE = 'viewInvalidRate';
    public const TYPE_VIEW_UNIQUE = 'viewUnique';
    public const TYPE_CLICK = 'click';
    public const TYPE_CLICK_ALL = 'clickAll';
    public const TYPE_CLICK_INVALID_RATE = 'clickInvalidRate';
    public const TYPE_CPC = 'cpc';
    public const TYPE_CPM = 'cpm';
    public const TYPE_SUM = 'sum';
    public const TYPE_SUM_BY_PAYMENT = 'sumPayment';
    public const TYPE_CTR = 'ctr';
    public const TYPE_STATS = 'stats';
    public const TYPE_STATS_REPORT = 'statsReport';

    public function fetchView(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult;

    public function fetchViewAll(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult;

    public function fetchViewInvalidRate(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult;

    public function fetchViewUnique(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult;

    public function fetchClick(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult;

    public function fetchClickAll(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult;

    public function fetchClickInvalidRate(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult;

    public function fetchCpc(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult;

    public function fetchCpm(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult;

    public function fetchSum(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult;

    public function fetchSumPayment(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult;

    public function fetchCtr(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult;

    public function fetchStats(
        ?string $advertiserId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): DataCollection;

    public function fetchStatsTotal(
        ?string $advertiserId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): Total;

    public function fetchStatsToReport(
        ?array $advertiserIds,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        bool $showAdvertisers = false
    ): DataCollection;

    public function fetchStatsConversion(
        int $advertiserId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?int $campaignId = null
    ): ConversionDataCollection;

    public function aggregateStatistics(DateTimeInterface $dateStart, DateTimeInterface $dateEnd): void;
}
