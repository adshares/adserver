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

namespace Adshares\Adserver\Repository\Advertiser;

use Adshares\Adserver\Exceptions\Advertiser\MissingEventsException;
use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Http\Requests\Filter\FilterCollection;
use Adshares\Adserver\Models\Campaign;
use Adshares\Adserver\Models\PaymentReport;
use Adshares\Adserver\Utilities\DateUtils;
use Adshares\Adserver\Utilities\SqlUtils;
use Adshares\Advertiser\Dto\Result\ChartResult;
use Adshares\Advertiser\Dto\Result\Stats\Calculation;
use Adshares\Advertiser\Dto\Result\Stats\ConversionDataCollection;
use Adshares\Advertiser\Dto\Result\Stats\ConversionDataEntry;
use Adshares\Advertiser\Dto\Result\Stats\DataCollection;
use Adshares\Advertiser\Dto\Result\Stats\DataEntry;
use Adshares\Advertiser\Dto\Result\Stats\ReportCalculation;
use Adshares\Advertiser\Dto\Result\Stats\Total;
use Adshares\Advertiser\Repository\StatsRepository;
use Adshares\Common\Domain\ValueObject\ChartResolution;
use DateTime;
use DateTimeInterface;
use DateTimeZone;

use function bin2hex;

class MySqlStatsRepository implements StatsRepository
{
    private const PLACEHOLDER_FOR_EMPTY_DOMAIN = 'N/A';

    private const SQL_QUERY_SELECT_FIRST_EVENT_ID_FROM_DATE_RANGE = <<<SQL
SELECT id
FROM event_logs
WHERE created_at BETWEEN ? AND ?
LIMIT 1;
SQL;

    private const SQL_QUERY_SELECT_FIRST_CONVERSION_ID_FROM_DATE_RANGE = <<<SQL
SELECT id
FROM conversions
WHERE created_at BETWEEN ? AND ?
LIMIT 1;
SQL;

    private const DELETE_FROM_EVENT_LOGS_HOURLY_WHERE_HOUR_TIMESTAMP = <<<SQL
DELETE FROM event_logs_hourly WHERE hour_timestamp = ?;
SQL;

    private const DELETE_FROM_EVENT_LOGS_HOURLY_STATS_WHERE_HOUR_TIMESTAMP = <<<SQL
DELETE FROM event_logs_hourly_stats WHERE hour_timestamp = ?;
SQL;

    private const DELETE_FROM_CONVERSIONS_HOURLY_WHERE_HOUR_TIMESTAMP = <<<SQL
DELETE FROM conversions_hourly WHERE hour_timestamp = ?;
SQL;

    private const EVENT_LOG_STATISTICS_SUBQUERY = <<<SQL
SELECT IF(e.event_type = 'view' AND e.is_view_clicked = 1 AND e.event_value_currency IS NOT NULL AND
           e.payment_status = 0, 1, 0)                                                            AS clicks,
        IF(e.event_type = 'view' AND e.event_value_currency IS NOT NULL AND e.payment_status = 0, 1,
           0)                                                                                     AS views,
        IF(e.event_value_currency IS NOT NULL AND e.payment_status = 0, e.event_value_currency, 0) +
        IFNULL((SELECT SUM(event_value_currency) FROM conversions WHERE event_logs_id = e.id), 0) AS cost,
        0                                                                                         AS cost_payment,
        IF(e.event_type = 'view' AND e.is_view_clicked = 1, 1, 0)                                 AS is_click,
        IF(e.event_type = 'view', 1, 0)                                                           AS is_view,
        IFNULL(e.user_id, e.tracking_id)                                                          AS user_id,
        IFNULL(e.domain, '')                                                                      AS domain,
        e.banner_id                                                                               AS banner_id,
        e.campaign_id                                                                             AS campaign_id,
        e.advertiser_id                                                                           AS advertiser_id
 FROM event_logs e
 WHERE e.created_at BETWEEN ? AND ?

 UNION ALL

 SELECT 0                               AS clicks,
        0                               AS views,
        0                               AS cost,
        IFNULL(event_value_currency, 0) AS cost_payment,
        0                               AS is_click,
        0                               AS is_view,
        ''                              AS user_id,
        IFNULL(domain, '')              AS domain,
        banner_id                       AS banner_id,
        campaign_id                     AS campaign_id,
        advertiser_id                   AS advertiser_id
 FROM event_logs
 WHERE payment_id IN (
     SELECT id
     FROM payments
     WHERE created_at BETWEEN ? AND ?
 )

 UNION ALL

 SELECT 0                                 AS clicks,
        0                                 AS views,
        0                                 AS cost,
        IFNULL(c.event_value_currency, 0) AS cost_payment,
        0                                 AS is_click,
        0                                 AS is_view,
        ''                                AS user_id,
        IFNULL(e.domain, '')              AS domain,
        e.banner_id                       AS banner_id,
        e.campaign_id                     AS campaign_id,
        e.advertiser_id                   AS advertiser_id
 FROM conversions c
          JOIN event_logs e ON e.id = c.event_logs_id
 WHERE c.payment_id IN (
     SELECT id
     FROM payments
     WHERE created_at BETWEEN ? AND ?
 )
SQL;

    private const INSERT_EVENT_LOGS_HOURLY_GROUPED_BY_DOMAIN = <<<SQL
INSERT INTO event_logs_hourly (`advertiser_id`, `campaign_id`, `banner_id`, `domain`, `clicks`, `views`, `cost`,
                               `cost_payment`, `clicks_all`, `views_all`, `views_unique`, `hour_timestamp`)
SELECT s.advertiser_id                                            AS advertiser_id,
       s.campaign_id                                              AS campaign_id,
       s.banner_id                                                AS banner_id,
       s.domain                                                   AS domain,
       SUM(s.clicks)                                              AS clicks,
       SUM(s.views)                                               AS views,
       SUM(s.cost)                                                AS cost,
       SUM(s.cost_payment)                                        AS cost_payment,
       SUM(s.is_click)                                            AS clicks_all,
       SUM(s.is_view)                                             AS views_all,
       COUNT(DISTINCT (CASE WHEN s.views = 1 THEN s.user_id END)) AS views_unique,
       ?                                                          AS hour_timestamp
FROM (%s) s
GROUP BY 1, 2, 3, 4
HAVING clicks > 0
    OR views > 0
    OR cost > 0
    OR cost_payment > 0
    OR clicks_all > 0
    OR views_all > 0
    OR views_unique > 0;
SQL;

    private const INSERT_EVENT_LOGS_HOURLY_STATS = <<<SQL
INSERT INTO event_logs_hourly_stats (advertiser_id,
                                     campaign_id,
                                     banner_id,
                                     cost,
                                     cost_payment,
                                     clicks,
                                     views,
                                     clicks_all,
                                     views_all,
                                     views_unique,
                                     hour_timestamp)
SELECT s.advertiser_id                                            AS advertiser_id,
       s.campaign_id                                              AS campaign_id,
       s.banner_id                                                AS banner_id,
       SUM(s.cost)                                                AS cost,
       SUM(s.cost_payment)                                        AS cost_payment,
       SUM(s.clicks)                                              AS clicks,
       SUM(s.views)                                               AS views,
       SUM(s.is_click)                                            AS clicks_all,
       SUM(s.is_view)                                             AS views_all,
       COUNT(DISTINCT (CASE WHEN s.views = 1 THEN s.user_id END)) AS views_unique,
       ?                                                          AS hour_timestamp
FROM (%s) s
GROUP BY 1, 2, 3
HAVING clicks > 0
    OR views > 0
    OR cost > 0
    OR cost_payment > 0
    OR clicks_all > 0
    OR views_all > 0
    OR views_unique > 0;
SQL;

    private const INSERT_EVENT_LOGS_HOURLY_STATS_GROUPED_BY_CAMPAIGN = <<<SQL
INSERT INTO event_logs_hourly_stats (`advertiser_id`, `campaign_id`, `clicks`, `views`, `cost`, `cost_payment`,
                                     `clicks_all`, `views_all`, `views_unique`, `hour_timestamp`)
SELECT s.advertiser_id                                            AS advertiser_id,
       s.campaign_id                                              AS campaign_id,
       SUM(s.clicks)                                              AS clicks,
       SUM(s.views)                                               AS views,
       SUM(s.cost)                                                AS cost,
       SUM(s.cost_payment)                                        AS cost_payment,
       SUM(s.is_click)                                            AS clicks_all,
       SUM(s.is_view)                                             AS views_all,
       COUNT(DISTINCT (CASE WHEN s.views = 1 THEN s.user_id END)) AS views_unique,
       ?                                                          AS hour_timestamp
FROM (%s) s
GROUP BY 1, 2
HAVING clicks > 0
    OR views > 0
    OR cost > 0
    OR cost_payment > 0
    OR clicks_all > 0
    OR views_all > 0
    OR views_unique > 0;
SQL;

    private const INSERT_CONVERSIONS_HOURLY = <<<SQL
INSERT INTO conversions_hourly (conversion_definition_id,
                                advertiser_id,
                                campaign_id,
                                cost,
                                occurrences,
                                hour_timestamp)
SELECT s.conversion_definition_id AS conversion_definition_id,
       c.user_id                  AS advertiser_id,
       c.id                       AS campaign_id,
       SUM(s.cost)                AS cost,
       COUNT(1)                   AS occurrences,
       ?      AS hour_timestamp
FROM (
         SELECT group_id,
                conversion_definition_id,
                IFNULL(SUM(event_value_currency), 0) AS cost
         FROM conversions
         WHERE created_at BETWEEN ? AND ?
           AND payment_id IS NOT NULL
         GROUP BY 1, 2
     ) s
         JOIN conversion_definitions cd on s.conversion_definition_id = cd.id
         JOIN campaigns c on cd.campaign_id = c.id
GROUP BY 1;
SQL;

    public function fetchView(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_VIEW,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId,
            $filters,
        );

        return new ChartResult($result);
    }

    public function fetchViewAll(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_VIEW_ALL,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId,
            $filters,
        );

        return new ChartResult($result);
    }

    public function fetchViewInvalidRate(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult {
        $resultViewsAll = $this->fetch(
            StatsRepository::TYPE_VIEW_ALL,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId,
            $filters,
        );

        $resultViews = $this->fetch(
            StatsRepository::TYPE_VIEW,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId,
            $filters,
        );

        $result = [];

        $rowCount = count($resultViews);

        for ($i = 0; $i < $rowCount; $i++) {
            $result[] = [
                $resultViews[$i][0],
                self::calculateInvalidRate((int)$resultViewsAll[$i][1], (int)$resultViews[$i][1]),
            ];
        }

        return new ChartResult($result);
    }

    public function fetchViewUnique(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_VIEW_UNIQUE,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId,
            $filters,
        );

        return new ChartResult($result);
    }

    public function fetchClick(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_CLICK,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId,
            $filters,
        );

        return new ChartResult($result);
    }

    public function fetchClickAll(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_CLICK_ALL,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId,
            $filters,
        );

        return new ChartResult($result);
    }

    public function fetchClickInvalidRate(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult {
        $resultClicksAll = $this->fetch(
            StatsRepository::TYPE_CLICK_ALL,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId,
            $filters,
        );

        $resultClicks = $this->fetch(
            StatsRepository::TYPE_CLICK,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId,
            $filters,
        );

        $result = [];

        $rowCount = count($resultClicks);

        for ($i = 0; $i < $rowCount; $i++) {
            $result[] = [
                $resultClicks[$i][0],
                self::calculateInvalidRate((int)$resultClicksAll[$i][1], (int)$resultClicks[$i][1]),
            ];
        }

        return new ChartResult($result);
    }

    public function fetchCpc(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult {
        $resultSum = $this->fetch(
            StatsRepository::TYPE_SUM,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId,
            $filters,
        );

        $resultClicks = $this->fetch(
            StatsRepository::TYPE_CLICK,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId,
            $filters,
        );

        $result = [];

        $rowCount = count($resultClicks);

        for ($i = 0; $i < $rowCount; $i++) {
            $result[] = [
                $resultClicks[$i][0],
                self::calculateCpc((int)$resultSum[$i][1], (int)$resultClicks[$i][1]),
            ];
        }

        return new ChartResult($result);
    }

    public function fetchCpm(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult {
        $resultSum = $this->fetch(
            StatsRepository::TYPE_SUM,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId,
            $filters,
        );

        $resultViews = $this->fetch(
            StatsRepository::TYPE_VIEW,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId,
            $filters,
        );

        $result = [];

        $rowCount = count($resultViews);

        for ($i = 0; $i < $rowCount; $i++) {
            $result[] = [
                $resultViews[$i][0],
                self::calculateCpm((int)$resultSum[$i][1], (int)$resultViews[$i][1]),
            ];
        }

        return new ChartResult($result);
    }

    public function fetchSum(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_SUM,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId,
            $filters,
        );

        return new ChartResult($result);
    }

    public function fetchSumPayment(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_SUM_BY_PAYMENT,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId,
            $filters,
        );

        return new ChartResult($result);
    }

    public function fetchCtr(
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): ChartResult {
        $resultClicks = $this->fetch(
            StatsRepository::TYPE_CLICK,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId,
            $filters,
        );

        $resultViews = $this->fetch(
            StatsRepository::TYPE_VIEW,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId,
            $filters,
        );

        $result = [];

        $rowCount = count($resultViews);

        for ($i = 0; $i < $rowCount; $i++) {
            $result[] = [
                $resultViews[$i][0],
                self::calculateCtr((int)$resultClicks[$i][1], (int)$resultViews[$i][1]),
            ];
        }

        return new ChartResult($result);
    }

    public function fetchStats(
        ?string $advertiserId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): DataCollection {
        $dateThreshold = $this->getDateThresholdForLiveData($dateStart->getTimezone());

        $queryResult = [];
        $queryResultLive = [];

        if ($dateStart < $dateThreshold) {
            $queryResult = $this->fetchStatsAggregates(
                $advertiserId,
                $dateStart,
                min($dateEnd, (clone $dateThreshold)->modify('-1 second')),
                $campaignId,
                $filters
            );
        }

        if ($dateThreshold < $dateEnd) {
            $queryResultLive = $this->fetchStatsLive(
                $advertiserId,
                max($dateStart, $dateThreshold),
                $dateEnd,
                $campaignId,
                $filters,
            );
        }

        $checkAdvertiserId = $advertiserId === null;
        $checkBannerId = $campaignId !== null;

        $combined = [];
        $campaignIdToNameMap = [];
        $bannerIdToNameMap = [];
        $advertiserIdToEmailMap = [];
        foreach (array_merge($queryResult, $queryResultLive) as $row) {
            if ($checkAdvertiserId) {
                $advertiserId = $row->advertiser_id;
                $advertiserIdToEmailMap[$advertiserId] = $row->advertiser_name;
            } else {
                $advertiserId = 0;
            }
            $rowCampaignId = $row->campaign_id;
            $campaignIdToNameMap[$rowCampaignId] = $row->campaign_name;
            if ($checkBannerId) {
                $bannerId = $row->banner_id;
                $bannerIdToNameMap[$bannerId] = $row->banner_name;
            } else {
                $bannerId = 0;
            }

            if (!array_key_exists($advertiserId, $combined)) {
                $combined[$advertiserId] = [];
            }
            if (!array_key_exists($rowCampaignId, $combined[$advertiserId])) {
                $combined[$advertiserId][$rowCampaignId] = [];
            }
            if (!array_key_exists($bannerId, $combined[$advertiserId][$rowCampaignId])) {
                $combined[$advertiserId][$rowCampaignId][$bannerId] = [
                    'clicks' => (int)$row->clicks,
                    'views' => (int)$row->views,
                    'cost' => (int)$row->cost,
                ];
            } else {
                $combined[$advertiserId][$rowCampaignId][$bannerId]['clicks'] =
                    $combined[$advertiserId][$rowCampaignId][$bannerId]['clicks'] + (int)$row->clicks;
                $combined[$advertiserId][$rowCampaignId][$bannerId]['views'] =
                    $combined[$advertiserId][$rowCampaignId][$bannerId]['views'] + (int)$row->views;
                $combined[$advertiserId][$rowCampaignId][$bannerId]['cost'] =
                    $combined[$advertiserId][$rowCampaignId][$bannerId]['cost'] + (int)$row->cost;
            }
        }

        $result = [];
        foreach ($combined as $advertiserId => $advertiserData) {
            foreach ($advertiserData as $campaignId => $campaignData) {
                foreach ($campaignData as $bannerId => $bannerData) {
                    $clicks = $bannerData['clicks'];
                    $views = $bannerData['views'];
                    $cost = $bannerData['cost'];

                    $calculation = new Calculation(
                        $clicks,
                        $views,
                        self::calculateCtr($clicks, $views),
                        self::calculateCpc($cost, $clicks),
                        self::calculateCpm($cost, $views),
                        $cost
                    );

                    $result[] = new DataEntry(
                        $calculation,
                        $campaignId,
                        $campaignIdToNameMap[$campaignId],
                        $checkBannerId ? $bannerId : null,
                        $checkBannerId ? $bannerIdToNameMap[$bannerId] : null,
                        $checkAdvertiserId ? $advertiserId : null,
                        $checkAdvertiserId ? $advertiserIdToEmailMap[$advertiserId] : null
                    );
                }
            }
        }

        return new DataCollection($result);
    }

    public function fetchStatsTotal(
        ?string $advertiserId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        ?FilterCollection $filters = null,
    ): Total {
        $dateThreshold = $this->getDateThresholdForLiveData($dateStart->getTimezone());

        $rowCampaignId = null;
        $rowCampaignName = null;
        $queryResult = [];
        $queryResultLive = [];

        if ($dateStart < $dateThreshold) {
            $queryResult = $this->fetchStatsTotalAggregates(
                $advertiserId,
                $dateStart,
                min($dateEnd, (clone $dateThreshold)->modify('-1 second')),
                $campaignId,
                $filters,
            );
        }

        if ($dateThreshold < $dateEnd) {
            $queryResultLive = $this->fetchStatsTotalLive(
                $advertiserId,
                max($dateStart, $dateThreshold),
                $dateEnd,
                $campaignId,
                $filters,
            );
        }

        if (!empty($queryResult)) {
            $row = $queryResult[0];
            $clicks = (int)$row->clicks;
            $views = (int)$row->views;
            $cost = (int)$row->cost;
            if (null !== $campaignId) {
                $rowCampaignId = $row->campaign_id;
                $rowCampaignName = $row->campaign_name;
            }
        } else {
            $clicks = 0;
            $views = 0;
            $cost = 0;
        }

        if (!empty($queryResultLive)) {
            $row = $queryResultLive[0];
            $clicks += (int)$row->clicks;
            $views += (int)$row->views;
            $cost += (int)$row->cost;
            if (null !== $campaignId) {
                $rowCampaignId = $row->campaign_id;
                $rowCampaignName = $row->campaign_name;
            }
        }

        $calculation = new Calculation(
            $clicks,
            $views,
            self::calculateCtr($clicks, $views),
            self::calculateCpc($cost, $clicks),
            self::calculateCpm($cost, $views),
            $cost
        );

        if (null !== $campaignId && (null === $rowCampaignId || null === $rowCampaignName)) {
            $campaign = Campaign::fetchByUuid($campaignId);
            $rowCampaignId = $campaign->id ?? null;
            $rowCampaignName = $campaign->name ?? null;
        }

        return new Total($calculation, $rowCampaignId, $rowCampaignName);
    }

    public function fetchStatsToReport(
        ?array $advertiserIds,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null,
        bool $showAdvertisers = false
    ): DataCollection {
        $queryBuilder = (new MySqlAggregatedStatsQueryBuilder(StatsRepository::TYPE_STATS_REPORT))
            ->setDateRange($dateStart, $dateEnd)
            ->appendDomainGroupBy()
            ->appendCampaignIdGroupBy()
            ->appendBannerIdGroupBy();

        if (null !== $advertiserIds) {
            $queryBuilder->setAdvertiserIds($advertiserIds);
        }
        if ($showAdvertisers) {
            $queryBuilder->appendAdvertiserIdGroupBy();
        }
        if ($campaignId) {
            $queryBuilder->appendCampaignIdWhereClause($campaignId);
        }

        $query = $queryBuilder->build();

        $queryResult = $this->executeQuery($query, $dateStart);

        $result = [];
        foreach ($queryResult as $row) {
            $clicks = (int)$row->clicks;
            $clicksAll = (int)$row->clicksAll;
            $views = (int)$row->views;
            $viewsAll = (int)$row->viewsAll;
            $cost = (int)$row->cost;

            $calculation = new ReportCalculation(
                $clicks,
                $clicksAll,
                self::calculateInvalidRate($clicksAll, $clicks),
                $views,
                $viewsAll,
                self::calculateInvalidRate($viewsAll, $views),
                (int)$row->viewsUnique,
                self::calculateCtr($clicks, $views),
                self::calculateCpc($cost, $clicks),
                self::calculateCpm($cost, $views),
                $cost,
                $row->domain ?: self::PLACEHOLDER_FOR_EMPTY_DOMAIN
            );

            if ($showAdvertisers) {
                $selectedAdvertiserId = $row->advertiser_id;
                $selectedAdvertiserEmail = $row->advertiser_email;
            } else {
                $selectedAdvertiserId = null;
                $selectedAdvertiserEmail = null;
            }
            $result[] = new DataEntry(
                $calculation,
                $row->campaign_id,
                $row->campaign_name,
                $row->banner_id,
                $row->banner_name,
                $selectedAdvertiserId,
                $selectedAdvertiserEmail
            );
        }

        $resultWithoutEvents =
            $this->getDataEntriesWithoutEvents(
                $queryResult,
                $this->fetchAllBannersWithCampaignAndUser($advertiserIds, $campaignId),
                $showAdvertisers
            );

        return new DataCollection(array_merge($result, $resultWithoutEvents));
    }

    public function aggregateStatistics(DateTimeInterface $dateStart, DateTimeInterface $dateEnd): void
    {
        if (
            empty(
                $this->executeQuery(
                    self::SQL_QUERY_SELECT_FIRST_EVENT_ID_FROM_DATE_RANGE,
                    $dateStart,
                    [$dateStart, $dateEnd]
                )
            )
        ) {
            throw new MissingEventsException(
                sprintf(
                    'No events in range from %s to %s',
                    $dateStart->format(DateTimeInterface::ATOM),
                    $dateEnd->format(DateTimeInterface::ATOM)
                )
            );
        }

        DB::beginTransaction();
        $this->executeQuery(
            self::DELETE_FROM_EVENT_LOGS_HOURLY_WHERE_HOUR_TIMESTAMP,
            $dateStart,
            [$dateStart]
        );
        $this->executeQuery(
            sprintf(self::INSERT_EVENT_LOGS_HOURLY_GROUPED_BY_DOMAIN, self::EVENT_LOG_STATISTICS_SUBQUERY),
            $dateStart,
            [$dateStart, $dateStart, $dateEnd, $dateStart, $dateEnd, $dateStart, $dateEnd]
        );

        $this->executeQuery(
            self::DELETE_FROM_EVENT_LOGS_HOURLY_STATS_WHERE_HOUR_TIMESTAMP,
            $dateStart,
            [$dateStart]
        );
        $this->executeQuery(
            sprintf(self::INSERT_EVENT_LOGS_HOURLY_STATS, self::EVENT_LOG_STATISTICS_SUBQUERY),
            $dateStart,
            [$dateStart, $dateStart, $dateEnd, $dateStart, $dateEnd, $dateStart, $dateEnd]
        );
        $this->executeQuery(
            sprintf(self::INSERT_EVENT_LOGS_HOURLY_STATS_GROUPED_BY_CAMPAIGN, self::EVENT_LOG_STATISTICS_SUBQUERY),
            $dateStart,
            [$dateStart, $dateStart, $dateEnd, $dateStart, $dateEnd, $dateStart, $dateEnd]
        );
        DB::commit();

        if (
            !empty(
                $this->executeQuery(
                    self::SQL_QUERY_SELECT_FIRST_CONVERSION_ID_FROM_DATE_RANGE,
                    $dateStart,
                    [$dateStart, $dateEnd]
                )
            )
        ) {
            DB::beginTransaction();
            $this->executeQuery(
                self::DELETE_FROM_CONVERSIONS_HOURLY_WHERE_HOUR_TIMESTAMP,
                $dateStart,
                [$dateStart]
            );
            $this->executeQuery(
                self::INSERT_CONVERSIONS_HOURLY,
                $dateStart,
                [$dateStart, $dateStart, $dateEnd]
            );
            DB::commit();
        }
    }

    private function fetch(
        string $type,
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId,
        ?FilterCollection $filters = null,
    ): array {
        $dateTimeZone = $dateStart->getTimezone();
        $dateThreshold = $this->getDateThresholdForLiveData($dateTimeZone);

        $concatenatedResult = [];

        if ($dateStart < $dateThreshold) {
            $queryResult = $this->fetchAggregates(
                $type,
                $advertiserId,
                $resolution,
                $dateStart,
                min($dateEnd, (clone $dateThreshold)->modify('-1 second')),
                $campaignId,
                $filters,
            );

            $concatenatedResult = self::concatenateDateColumns($dateTimeZone, $queryResult, $resolution);
        }

        if ($dateThreshold < $dateEnd) {
            $queryResultLive = $this->fetchLive(
                $type,
                $advertiserId,
                $resolution,
                max($dateStart, $dateThreshold),
                $dateEnd,
                $campaignId,
                $filters,
            );

            $concatenatedResultLive = self::concatenateDateColumns($dateTimeZone, $queryResultLive, $resolution);
            $concatenatedResult = self::joinResultWithResultLive($concatenatedResult, $concatenatedResultLive);
        }

        $emptyResult = self::createEmptyResult($dateTimeZone, $resolution, $dateStart, $dateEnd);
        $joinedResult = self::joinResultWithEmpty($concatenatedResult, $emptyResult);

        return self::overwriteStartDate($dateStart, self::mapResult($joinedResult));
    }

    private function fetchAggregates(
        string $type,
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId,
        ?FilterCollection $filters = null,
    ): array {
        $queryBuilder = (new MySqlAggregatedStatsQueryBuilder($type))
            ->setAdvertiserIds([$advertiserId])
            ->setDateRange($dateStart, $dateEnd)
            ->appendResolution($resolution);

        if ($campaignId) {
            $queryBuilder->appendCampaignIdWhereClause($campaignId);
        }
        $queryBuilder->appendAnyBannerId();
        if (null !== $filters) {
            $queryBuilder->appendMediumWhereClause(
                $filters->getFilterByName('medium'),
                $filters->getFilterByName('vendor'),
            );
        }

        $query = $queryBuilder->build();

        return $this->executeQuery($query, $dateStart);
    }

    private function fetchLive(
        string $type,
        string $advertiserId,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId,
        ?FilterCollection $filters = null,
    ): array {
        if (
            in_array($type, [
            StatsRepository::TYPE_SUM,
            StatsRepository::TYPE_SUM_BY_PAYMENT,
            ])
        ) {
            return [];
        }

        $queryBuilder = (new MySqlLiveStatsQueryBuilder($type))
            ->setAdvertiserId($advertiserId)
            ->setDateRange($dateStart, $dateEnd)
            ->appendResolution($resolution);

        if ($campaignId) {
            $queryBuilder->appendCampaignIdWhereClause($campaignId);
        }
        if (null !== $filters) {
            $queryBuilder->appendMediumWhereClause(
                $filters->getFilterByName('medium'),
                $filters->getFilterByName('vendor'),
            );
        }

        $query = $queryBuilder->build();

        return $this->executeQuery($query, $dateStart);
    }

    private function executeQuery(string $query, DateTimeInterface $dateStart, array $bindings = []): array
    {
        return SqlUtils::executeTimezoneAwareQuery(
            new DateTimeZone($dateStart->format('O')),
            fn() => DB::select($query, $bindings),
        );
    }

    private static function concatenateDateColumns(
        DateTimeZone $dateTimeZone,
        array $result,
        ChartResolution $resolution,
    ): array {
        if (count($result) === 0) {
            return [];
        }

        $formattedResult = [];

        $date = (new DateTime())->setTimezone($dateTimeZone);
        if ($resolution !== ChartResolution::HOUR) {
            $date->setTime(0, 0);
        }

        foreach ($result as $row) {
            if ($resolution === ChartResolution::HOUR) {
                $date->setTime($row->h, 0);
            }

            switch ($resolution) {
                case ChartResolution::HOUR:
                case ChartResolution::DAY:
                    $date->setDate($row->y, $row->m, $row->d);
                    break;
                case ChartResolution::WEEK:
                    $yearweek = (string)$row->yw;
                    $year = (int)substr($yearweek, 0, 4);
                    $week = (int)substr($yearweek, 4);
                    $date->setISODate($year, $week, 1);
                    break;
                case ChartResolution::MONTH:
                    $date->setDate($row->y, $row->m, 1);
                    break;
                case ChartResolution::QUARTER:
                    $month = $row->q * 3 - 2;
                    $date->setDate($row->y, $month, 1);
                    break;
//                case ChartResolution::YEAR:
                default:
                    $date->setDate($row->y, 1, 1);
                    break;
            }

            $d = $date->format(DateTimeInterface::ATOM);
            $formattedResult[$d] = (int)$row->c;
        }

        return $formattedResult;
    }

    private static function createEmptyResult(
        DateTimeZone $dateTimeZone,
        ChartResolution $resolution,
        DateTime $dateStart,
        DateTime $dateEnd
    ): array {
        $dates = [];
        $date = DateUtils::createSanitizedStartDate($dateTimeZone, $resolution, $dateStart);

        while ($date < $dateEnd) {
            $dates[] = $date->format(DateTimeInterface::ATOM);
            DateUtils::advanceStartDate($resolution, $date);
        }

        if (empty($dates)) {
            $dates[] = $date->format(DateTimeInterface::ATOM);
        }

        $result = [];
        foreach ($dates as $dateEntry) {
            $result[$dateEntry] = 0;
        }

        return $result;
    }

    private static function joinResultWithEmpty(array $formattedResult, array $emptyResult): array
    {
        foreach ($emptyResult as $key => $value) {
            if (isset($formattedResult[$key])) {
                $emptyResult[$key] = $formattedResult[$key];
            }
        }

        return $emptyResult;
    }

    private static function joinResultWithResultLive(array $result, array $resultLive): array
    {
        foreach ($resultLive as $key => $value) {
            if (isset($result[$key])) {
                $result[$key] = $result[$key] + $resultLive[$key];
            } else {
                $result[$key] = $resultLive[$key];
            }
        }

        return $result;
    }

    private static function mapResult(array $joinedResult): array
    {
        $result = [];
        foreach ($joinedResult as $key => $value) {
            $result[] = [$key, $value];
        }

        return $result;
    }

    private static function overwriteStartDate(DateTimeInterface $dateStart, array $result): array
    {
        if (count($result) > 0) {
            $result[0][0] = $dateStart->format(DateTimeInterface::ATOM);
        }

        return $result;
    }

    private static function calculateCpc(int $cost, int $clicks): int
    {
        return (0 === $clicks) ? 0 : (int)round($cost / $clicks);
    }

    private static function calculateCpm(int $cost, int $views): int
    {
        return (0 === $views) ? 0 : (int)round($cost / $views * 1000);
    }

    private static function calculateCtr(int $clicks, int $views): float
    {
        return (0 === $views) ? 0 : $clicks / $views;
    }

    private static function calculateInvalidRate(int $totalCount, int $validCount): float
    {
        return (0 === $totalCount) ? 0 : ($totalCount - $validCount) / $totalCount;
    }

    private function getDataEntriesWithoutEvents(
        array $queryResult,
        array $allBanners,
        bool $showAdvertisers
    ): array {
        $result = [];

        foreach ($allBanners as $banner) {
            $bannerId = $banner->banner_id;
            $isBannerPresent = false;

            foreach ($queryResult as $row) {
                if ($row->banner_id === $bannerId) {
                    $isBannerPresent = true;
                    break;
                }
            }

            if (!$isBannerPresent) {
                $calculation =
                    new ReportCalculation(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, self::PLACEHOLDER_FOR_EMPTY_DOMAIN);
                if ($showAdvertisers) {
                    $selectedAdvertiserId = $banner->user_id;
                    $selectedAdvertiserEmail = $banner->user_email;
                } else {
                    $selectedAdvertiserId = null;
                    $selectedAdvertiserEmail = null;
                }
                $result[] =
                    new DataEntry(
                        $calculation,
                        $banner->campaign_id,
                        $banner->campaign_name,
                        $bannerId,
                        $banner->banner_name,
                        $selectedAdvertiserId,
                        $selectedAdvertiserEmail
                    );
            }
        }

        return $result;
    }

    private function fetchAllBannersWithCampaignAndUser(?array $advertiserIds, ?string $campaignId): array
    {
        $query = <<<SQL
SELECT u.id    AS user_id,
       u.email AS user_email,
       c.id    AS campaign_id,
       c.name  AS campaign_name,
       b.id    AS banner_id,
       b.name  AS banner_name
FROM users u
         JOIN campaigns c ON u.id = c.user_id
         JOIN banners b ON b.campaign_id = c.id
WHERE c.deleted_at IS NULL
SQL;

        if (null !== $advertiserIds) {
            $query .= empty($advertiserIds) ?
                ' AND 0=1' :
                sprintf(
                    ' AND u.uuid IN (%s)',
                    implode(',', array_map(fn($id) => sprintf('0x%s', $id), $advertiserIds))
                );
        }
        if (null !== $campaignId) {
            $query .= sprintf(' AND c.uuid = 0x%s', $campaignId);
        }

        return DB::select($query);
    }

    private function fetchStatsAggregates(
        ?string $advertiserId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId,
        ?FilterCollection $filters = null,
    ): array {
        $queryBuilder =
            (new MySqlAggregatedStatsQueryBuilder(StatsRepository::TYPE_STATS))
                ->setDateRange($dateStart, $dateEnd)
                ->appendCampaignIdGroupBy();

        if (null !== $advertiserId) {
            $queryBuilder->setAdvertiserIds([$advertiserId]);
        } else {
            $queryBuilder->appendAdvertiserIdGroupBy();
        }

        if ($campaignId) {
            $queryBuilder->appendCampaignIdWhereClause($campaignId)->appendBannerIdGroupBy();
        } else {
            $queryBuilder->appendAnyBannerId();
        }
        if (null !== $filters) {
            $queryBuilder->appendMediumWhereClause(
                $filters->getFilterByName('medium'),
                $filters->getFilterByName('vendor'),
            );
        }

        $query = $queryBuilder->build();

        return $this->executeQuery($query, $dateStart);
    }

    private function fetchStatsLive(
        ?string $advertiserId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId,
        ?FilterCollection $filters = null,
    ): array {
        $queryBuilder =
            (new MySqlLiveStatsQueryBuilder(StatsRepository::TYPE_STATS))
                ->setDateRange($dateStart, $dateEnd)
                ->appendCampaignIdGroupBy();

        if (null !== $advertiserId) {
            $queryBuilder->setAdvertiserId($advertiserId);
        } else {
            $queryBuilder->appendAdvertiserIdGroupBy();
        }

        if ($campaignId) {
            $queryBuilder->appendCampaignIdWhereClause($campaignId)->appendBannerIdGroupBy();
        }
        if (null !== $filters) {
            $queryBuilder->appendMediumWhereClause(
                $filters->getFilterByName('medium'),
                $filters->getFilterByName('vendor'),
            );
        }

        $query = $queryBuilder->build();

        return $this->executeQuery($query, $dateStart);
    }

    private function fetchStatsTotalAggregates(
        ?string $advertiserId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId,
        ?FilterCollection $filters = null,
    ): array {
        $queryBuilder =
            (new MySqlAggregatedStatsQueryBuilder(StatsRepository::TYPE_STATS))
                ->setDateRange($dateStart, $dateEnd);

        if (null !== $advertiserId) {
            $queryBuilder->setAdvertiserIds([$advertiserId]);
        }

        if ($campaignId) {
            $queryBuilder->appendCampaignIdWhereClause($campaignId)->appendCampaignIdGroupBy();
        }
        if (null !== $filters) {
            $queryBuilder->appendMediumWhereClause(
                $filters->getFilterByName('medium'),
                $filters->getFilterByName('vendor'),
            );
        }
        $queryBuilder->appendAnyBannerId();

        $query = $queryBuilder->build();

        return $this->executeQuery($query, $dateStart);
    }

    private function fetchStatsTotalLive(
        ?string $advertiserId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId,
        ?FilterCollection $filters = null,
    ): array {
        $queryBuilder =
            (new MySqlLiveStatsQueryBuilder(StatsRepository::TYPE_STATS))
                ->setDateRange($dateStart, $dateEnd);

        if (null !== $advertiserId) {
            $queryBuilder->setAdvertiserId($advertiserId);
        }

        if ($campaignId) {
            $queryBuilder->appendCampaignIdWhereClause($campaignId)->appendCampaignIdGroupBy();
        }
        if (null !== $filters) {
            $queryBuilder->appendMediumWhereClause(
                $filters->getFilterByName('medium'),
                $filters->getFilterByName('vendor'),
            );
        }

        $query = $queryBuilder->build();

        return $this->executeQuery($query, $dateStart);
    }

    private function getDateThresholdForLiveData(DateTimeZone $dateTimeZone): DateTime
    {
        $dateThreshold = DateUtils::getDateTimeRoundedToCurrentHour(new DateTime('now', $dateTimeZone));

        $timestampPreviousHour = $dateThreshold->getTimestamp() - DateUtils::HOUR;
        $paymentReport = PaymentReport::fetchById($timestampPreviousHour);

        if (null === $paymentReport || $paymentReport->isNew() || $paymentReport->isFailed()) {
            $dateThreshold->modify('-1 hour');
        }

        return $dateThreshold;
    }

    public function fetchStatsConversion(
        int $advertiserId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?int $campaignId = null
    ): ConversionDataCollection {
        $builder = DB::table('conversions_hourly AS ch')
            ->where('ch.hour_timestamp', '>=', $dateStart)
            ->where('ch.hour_timestamp', '<=', $dateEnd)
            ->where('ch.advertiser_id', $advertiserId)
            ->selectRaw(
                'ch.campaign_id AS campaign_id,'
                . 'cd.uuid AS uuid,'
                . 'SUM(ch.cost) AS cost,'
                . 'SUM(ch.occurrences) AS occurrences'
            )
            ->join('conversion_definitions AS cd', 'ch.conversion_definition_id', '=', 'cd.id')
            ->groupBy('ch.campaign_id', 'ch.conversion_definition_id');

        if (null !== $campaignId) {
            $builder->where('ch.campaign_id', $campaignId);
        }

        $queryResult = $builder->get();

        $result = [];

        foreach ($queryResult as $entry) {
            $result[] = new ConversionDataEntry(
                $entry->campaign_id,
                bin2hex($entry->uuid),
                (int)$entry->cost,
                (int)$entry->occurrences
            );
        }

        return new ConversionDataCollection($result);
    }
}
