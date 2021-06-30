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

namespace Adshares\Adserver\Repository\Publisher;

use Adshares\Adserver\Facades\DB;
use Adshares\Adserver\Models\Site;
use Adshares\Adserver\Utilities\DateUtils;
use Adshares\Publisher\Dto\Result\ChartResult;
use Adshares\Publisher\Dto\Result\Stats\Calculation;
use Adshares\Publisher\Dto\Result\Stats\DataCollection;
use Adshares\Publisher\Dto\Result\Stats\DataEntry;
use Adshares\Publisher\Dto\Result\Stats\ReportCalculation;
use Adshares\Publisher\Dto\Result\Stats\Total;
use Adshares\Publisher\Repository\StatsRepository;
use DateTime;
use DateTimeInterface;
use DateTimeZone;

use function bin2hex;

class MySqlStatsRepository implements StatsRepository
{
    private const PLACEHOLDER_FOR_EMPTY_DOMAIN = 'N/A';

    public function fetchView(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_VIEW,
            $publisherId,
            $resolution,
            $dateStart,
            $dateEnd,
            $siteId
        );

        return new ChartResult($result);
    }

    public function fetchViewAll(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_VIEW_ALL,
            $publisherId,
            $resolution,
            $dateStart,
            $dateEnd,
            $siteId
        );

        return new ChartResult($result);
    }

    public function fetchViewInvalidRate(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $resultViewsAll = $this->fetch(
            StatsRepository::TYPE_VIEW_ALL,
            $publisherId,
            $resolution,
            $dateStart,
            $dateEnd,
            $siteId
        );

        $resultViews = $this->fetch(
            StatsRepository::TYPE_VIEW,
            $publisherId,
            $resolution,
            $dateStart,
            $dateEnd,
            $siteId
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
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_VIEW_UNIQUE,
            $publisherId,
            $resolution,
            $dateStart,
            $dateEnd,
            $siteId
        );

        return new ChartResult($result);
    }

    public function fetchClick(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_CLICK,
            $publisherId,
            $resolution,
            $dateStart,
            $dateEnd,
            $siteId
        );

        return new ChartResult($result);
    }

    public function fetchClickAll(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_CLICK_ALL,
            $publisherId,
            $resolution,
            $dateStart,
            $dateEnd,
            $siteId
        );

        return new ChartResult($result);
    }

    public function fetchClickInvalidRate(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $resultClicksAll = $this->fetch(
            StatsRepository::TYPE_CLICK_ALL,
            $publisherId,
            $resolution,
            $dateStart,
            $dateEnd,
            $siteId
        );

        $resultClicks = $this->fetch(
            StatsRepository::TYPE_CLICK,
            $publisherId,
            $resolution,
            $dateStart,
            $dateEnd,
            $siteId
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

    public function fetchRpc(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $resultSum = $this->fetch(
            StatsRepository::TYPE_REVENUE_BY_CASE,
            $publisherId,
            $resolution,
            $dateStart,
            $dateEnd,
            $siteId
        );

        $resultClicks = $this->fetch(
            StatsRepository::TYPE_CLICK,
            $publisherId,
            $resolution,
            $dateStart,
            $dateEnd,
            $siteId
        );

        $result = [];

        $rowCount = count($resultClicks);

        for ($i = 0; $i < $rowCount; $i++) {
            $result[] = [
                $resultClicks[$i][0],
                self::calculateRpc((int)$resultSum[$i][1], (int)$resultClicks[$i][1]),
            ];
        }

        return new ChartResult($result);
    }

    public function fetchRpm(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $resultSum = $this->fetch(
            StatsRepository::TYPE_REVENUE_BY_CASE,
            $publisherId,
            $resolution,
            $dateStart,
            $dateEnd,
            $siteId
        );

        $resultViews = $this->fetch(
            StatsRepository::TYPE_VIEW,
            $publisherId,
            $resolution,
            $dateStart,
            $dateEnd,
            $siteId
        );

        $result = [];

        $rowCount = count($resultViews);

        for ($i = 0; $i < $rowCount; $i++) {
            $result[] = [
                $resultViews[$i][0],
                self::calculateRpm((int)$resultSum[$i][1], (int)$resultViews[$i][1]),
            ];
        }

        return new ChartResult($result);
    }

    public function fetchSum(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_REVENUE_BY_CASE,
            $publisherId,
            $resolution,
            $dateStart,
            $dateEnd,
            $siteId
        );

        return new ChartResult($result);
    }

    public function fetchSumHour(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_REVENUE_BY_HOUR,
            $publisherId,
            $resolution,
            $dateStart,
            $dateEnd,
            $siteId
        );

        return new ChartResult($result);
    }

    public function fetchCtr(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $resultClicks = $this->fetch(
            StatsRepository::TYPE_CLICK,
            $publisherId,
            $resolution,
            $dateStart,
            $dateEnd,
            $siteId
        );

        $resultViews = $this->fetch(
            StatsRepository::TYPE_VIEW,
            $publisherId,
            $resolution,
            $dateStart,
            $dateEnd,
            $siteId
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
        ?string $publisherId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): DataCollection {
        $dateThreshold = $this->getDateThresholdForLiveData($dateStart->getTimezone());

        $queryResult = [];
        $queryResultLive = [];

        if ($dateStart < $dateThreshold) {
            $queryResult = $this->fetchStatsAggregates(
                $publisherId,
                $dateStart,
                min($dateEnd, (clone $dateThreshold)->modify('-1 second')),
                $siteId
            );
        }

        if ($dateThreshold < $dateEnd) {
            $queryResultLive = $this->fetchStatsLive(
                $publisherId,
                max($dateStart, $dateThreshold),
                $dateEnd,
                $siteId
            );
        }

        $checkPublisherId = $publisherId === null;
        $checkZoneId = $siteId !== null;

        $combined = [];
        $siteIdToNameMap = [];
        $zoneIdToNameMap = [];
        $publisherIdToEmailMap = [];
        foreach (array_merge($queryResult, $queryResultLive) as $row) {
            if ($checkPublisherId) {
                $publisherId = $row->publisher_id;
                $publisherIdToEmailMap[$publisherId] = $row->publisher_email;
            } else {
                $publisherId = 0;
            }
            $rowSiteId = $row->site_id;
            $siteIdToNameMap[$rowSiteId] = $row->site_name;
            if ($checkZoneId) {
                $zoneId = $row->zone_id;
                $zoneIdToNameMap[$zoneId] = $row->zone_name;
            } else {
                $zoneId = 0;
            }

            if (!array_key_exists($publisherId, $combined)) {
                $combined[$publisherId] = [];
            }
            if (!array_key_exists($rowSiteId, $combined[$publisherId])) {
                $combined[$publisherId][$rowSiteId] = [];
            }
            if (!array_key_exists($zoneId, $combined[$publisherId][$rowSiteId])) {
                $combined[$publisherId][$rowSiteId][$zoneId] = [
                    'clicks' => (int)$row->clicks,
                    'views' => (int)$row->views,
                    'revenue' => (int)$row->revenue,
                ];
            } else {
                $combined[$publisherId][$rowSiteId][$zoneId]['clicks'] =
                    $combined[$publisherId][$rowSiteId][$zoneId]['clicks'] + (int)$row->clicks;
                $combined[$publisherId][$rowSiteId][$zoneId]['views'] =
                    $combined[$publisherId][$rowSiteId][$zoneId]['views'] + (int)$row->views;
                $combined[$publisherId][$rowSiteId][$zoneId]['revenue'] =
                    $combined[$publisherId][$rowSiteId][$zoneId]['revenue'] + (int)$row->revenue;
            }
        }

        $result = [];
        foreach ($combined as $publisherId => $publisherData) {
            foreach ($publisherData as $siteId => $siteData) {
                foreach ($siteData as $zoneId => $zoneData) {
                    $clicks = $zoneData['clicks'];
                    $views = $zoneData['views'];
                    $revenue = $zoneData['revenue'];

                    $calculation = new Calculation(
                        $clicks,
                        $views,
                        self::calculateCtr($clicks, $views),
                        self::calculateRpc($revenue, $clicks),
                        self::calculateRpm($revenue, $views),
                        $revenue
                    );

                    $result[] = new DataEntry(
                        $calculation,
                        $siteId,
                        $siteIdToNameMap[$siteId],
                        $checkZoneId ? $zoneId : null,
                        $checkZoneId ? $zoneIdToNameMap[$zoneId] : null,
                        $checkPublisherId ? $publisherId : null,
                        $checkPublisherId ? $publisherIdToEmailMap[$publisherId] : null
                    );
                }
            }
        }

        return new DataCollection($result);
    }

    public function fetchStatsTotal(
        ?string $publisherId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): Total {
        $dateThreshold = $this->getDateThresholdForLiveData($dateStart->getTimezone());

        $rowSiteId = null;
        $rowSiteName = null;
        $queryResult = [];
        $queryResultLive = [];

        if ($dateStart < $dateThreshold) {
            $queryResult = $this->fetchStatsTotalAggregates(
                $publisherId,
                $dateStart,
                min($dateEnd, (clone $dateThreshold)->modify('-1 second')),
                $siteId
            );
        }

        if ($dateThreshold < $dateEnd) {
            $queryResultLive = $this->fetchStatsTotalLive(
                $publisherId,
                max($dateStart, $dateThreshold),
                $dateEnd,
                $siteId
            );
        }

        if (!empty($queryResult)) {
            $row = $queryResult[0];
            $clicks = (int)$row->clicks;
            $views = (int)$row->views;
            $revenue = (int)$row->revenue;
            if (null !== $siteId) {
                $rowSiteId = $row->site_id;
                $rowSiteName = $row->site_name;
            }
        } else {
            $clicks = 0;
            $views = 0;
            $revenue = 0;
        }

        if (!empty($queryResultLive)) {
            $row = $queryResultLive[0];
            $clicks += (int)$row->clicks;
            $views += (int)$row->views;
            $revenue += (int)$row->revenue;
            if (null !== $siteId) {
                $rowSiteId = $row->site_id;
                $rowSiteName = $row->site_name;
            }
        }

        $calculation = new Calculation(
            $clicks,
            $views,
            self::calculateCtr($clicks, $views),
            self::calculateRpc($revenue, $clicks),
            self::calculateRpm($revenue, $views),
            $revenue
        );

        if (null !== $siteId && (null === $rowSiteId || null === $rowSiteName)) {
            $site = Site::fetchByPublicId($siteId);
            $rowSiteId = $site->id ?? null;
            $rowSiteName = $site->name ?? null;
        }

        return new Total($calculation, $rowSiteId, $rowSiteName);
    }

    public function fetchStatsToReport(
        ?string $publisherId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): DataCollection {
        $queryBuilder = (new MySqlAggregatedStatsQueryBuilder(StatsRepository::TYPE_STATS_REPORT))
            ->setDateRange($dateStart, $dateEnd)
            ->appendDomainGroupBy()
            ->appendSiteIdGroupBy()
            ->appendZoneIdGroupBy();

        if (null !== $publisherId) {
            $queryBuilder->setPublisherId($publisherId);
        } else {
            $queryBuilder->appendPublisherIdGroupBy();
        }

        if ($siteId) {
            $queryBuilder->appendSiteIdWhereClause($siteId);
        }

        $query = $queryBuilder->build();

        $queryResult = $this->executeQuery($query, $dateStart);

        $result = [];
        foreach ($queryResult as $row) {
            $clicks = (int)$row->clicks;
            $clicksAll = (int)$row->clicksAll;
            $views = (int)$row->views;
            $viewsAll = (int)$row->viewsAll;
            $revenue = (int)$row->revenue;

            $calculation = new ReportCalculation(
                $clicks,
                $clicksAll,
                self::calculateInvalidRate($clicksAll, $clicks),
                $views,
                $viewsAll,
                self::calculateInvalidRate($viewsAll, $views),
                (int)$row->viewsUnique,
                self::calculateCtr($clicks, $views),
                self::calculateRpc($revenue, $clicks),
                self::calculateRpm($revenue, $views),
                $revenue,
                $row->domain ?: self::PLACEHOLDER_FOR_EMPTY_DOMAIN
            );

            if (null === $publisherId) {
                $selectedPublisherId = $row->publisher_id;
                $selectedPublisherEmail = $row->publisher_email;
            } else {
                $selectedPublisherId = null;
                $selectedPublisherEmail = null;
            }
            $result[] = new DataEntry(
                $calculation,
                $row->site_id,
                $row->site_name,
                $row->zone_id,
                $row->zone_name,
                $selectedPublisherId,
                $selectedPublisherEmail
            );
        }

        $resultWithoutEvents =
            $this->getDataEntriesWithoutEvents(
                $queryResult,
                $this->fetchAllZonesWithSiteAndUser($publisherId, $siteId),
                $publisherId
            );

        return new DataCollection(array_merge($result, $resultWithoutEvents));
    }

    private function fetch(
        string $type,
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId,
        ?string $zoneId = null
    ): array {
        $dateTimeZone = $dateStart->getTimezone();
        $dateThreshold = $this->getDateThresholdForLiveData($dateTimeZone);

        $concatenatedResult = [];

        if ($dateStart < $dateThreshold) {
            $queryResult = $this->fetchAggregates(
                $type,
                $publisherId,
                $resolution,
                $dateStart,
                min($dateEnd, (clone $dateThreshold)->modify('-1 second')),
                $siteId,
                $zoneId
            );

            $concatenatedResult = self::concatenateDateColumns($dateTimeZone, $queryResult, $resolution);
        }

        if ($dateThreshold < $dateEnd) {
            $queryResultLive = $this->fetchLive(
                $type,
                $publisherId,
                $resolution,
                max($dateStart, $dateThreshold),
                $dateEnd,
                $siteId,
                $zoneId
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
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId,
        ?string $zoneId = null
    ): array {
        $queryBuilder = (new MySqlAggregatedStatsQueryBuilder($type))
            ->setPublisherId($publisherId)
            ->setDateRange($dateStart, $dateEnd)
            ->appendResolution($resolution);

        if ($siteId) {
            $queryBuilder->appendSiteIdWhereClause($siteId);
        }

        if ($zoneId) {
            $queryBuilder->appendZoneIdWhereClause($zoneId);
        } else {
            $queryBuilder->appendAnyZoneId();
        }

        $query = $queryBuilder->build();

        return $this->executeQuery($query, $dateStart);
    }

    private function fetchLive(
        string $type,
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId,
        ?string $zoneId = null
    ): array {
        $queryBuilder = (new MySqlLiveStatsQueryBuilder($type))
            ->setPublisherId($publisherId)
            ->setDateRange($dateStart, $dateEnd)
            ->appendResolution($resolution);

        if ($siteId) {
            $queryBuilder->appendSiteIdWhereClause($siteId);
        }

        if ($zoneId) {
            $queryBuilder->appendZoneIdWhereClause($zoneId);
        }

        $query = $queryBuilder->build();

        return $this->executeQuery($query, $dateStart);
    }

    private function executeQuery(string $query, DateTimeInterface $dateStart): array
    {
        $dateTimeZone = new DateTimeZone($dateStart->format('O'));
        $tz = $this->setDbSessionTimezone($dateTimeZone);
        $queryResult = DB::select($query);
        if ($tz) {
            $this->unsetDbSessionTimeZone($tz);
        }

        return $queryResult;
    }

    private function setDbSessionTimezone(DateTimeZone $dateTimeZone): string
    {
        $tz = DB::selectOne('SELECT @@session.time_zone AS tz');
        DB::statement(sprintf("SET time_zone = '%s'", $dateTimeZone->getName()));
        return $tz->tz ?? '';
    }

    private function unsetDbSessionTimeZone($tz): void
    {
        DB::statement(sprintf("SET time_zone = '%s'", $tz));
    }

    private static function concatenateDateColumns(DateTimeZone $dateTimeZone, array $result, string $resolution): array
    {
        if (count($result) === 0) {
            return [];
        }

        $formattedResult = [];

        $date = (new DateTime())->setTimezone($dateTimeZone);
        if ($resolution !== StatsRepository::RESOLUTION_HOUR) {
            $date->setTime(0, 0, 0, 0);
        }

        foreach ($result as $row) {
            if ($resolution === StatsRepository::RESOLUTION_HOUR) {
                $date->setTime($row->h, 0, 0, 0);
            }

            switch ($resolution) {
                case StatsRepository::RESOLUTION_HOUR:
                case StatsRepository::RESOLUTION_DAY:
                    $date->setDate($row->y, $row->m, $row->d);
                    break;
                case StatsRepository::RESOLUTION_WEEK:
                    $yearweek = (string)$row->yw;
                    $year = (int)substr($yearweek, 0, 4);
                    $week = (int)substr($yearweek, 4);
                    $date->setISODate($year, $week, 1);
                    break;
                case StatsRepository::RESOLUTION_MONTH:
                    $date->setDate($row->y, $row->m, 1);
                    break;
                case StatsRepository::RESOLUTION_QUARTER:
                    $month = $row->q * 3 - 2;
                    $date->setDate($row->y, $month, 1);
                    break;
                case StatsRepository::RESOLUTION_YEAR:
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
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd
    ): array {
        $dates = [];
        $date = self::createSanitizedStartDate($dateTimeZone, $resolution, $dateStart);

        while ($date < $dateEnd) {
            $dates[] = $date->format(DateTimeInterface::ATOM);
            self::advanceDateTime($resolution, $date);
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

    private static function createSanitizedStartDate(
        DateTimeZone $dateTimeZone,
        string $resolution,
        DateTime $dateStart
    ): DateTime {
        $date = (clone $dateStart)->setTimezone($dateTimeZone);

        if ($resolution === StatsRepository::RESOLUTION_HOUR) {
            $date->setTime((int)$date->format('H'), 0, 0, 0);
        } else {
            $date->setTime(0, 0, 0, 0);
        }

        switch ($resolution) {
            case StatsRepository::RESOLUTION_HOUR:
            case StatsRepository::RESOLUTION_DAY:
                break;
            case StatsRepository::RESOLUTION_WEEK:
                $date->setISODate((int)$date->format('Y'), (int)$date->format('W'), 1);
                break;
            case StatsRepository::RESOLUTION_MONTH:
                $date->setDate((int)$date->format('Y'), (int)$date->format('m'), 1);
                break;
            case StatsRepository::RESOLUTION_QUARTER:
                $quarter = (int)floor((int)$date->format('m') - 1 / 3);
                $month = $quarter * 3 + 1;
                $date->setDate((int)$date->format('Y'), $month, 1);
                break;
            case StatsRepository::RESOLUTION_YEAR:
            default:
                $date->setDate((int)$date->format('Y'), 1, 1);
                break;
        }

        return $date;
    }

    private static function advanceDateTime(string $resolution, DateTime $date): void
    {
        switch ($resolution) {
            case StatsRepository::RESOLUTION_HOUR:
                $date->modify('+1 hour');
                break;
            case StatsRepository::RESOLUTION_DAY:
                $date->modify('tomorrow');
                break;
            case StatsRepository::RESOLUTION_WEEK:
                $date->modify('+7 days');
                break;
            case StatsRepository::RESOLUTION_MONTH:
                $date->modify('first day of next month');
                break;
            case StatsRepository::RESOLUTION_QUARTER:
                $date->modify('first day of next month');
                $date->modify('first day of next month');
                $date->modify('first day of next month');
                break;
            case StatsRepository::RESOLUTION_YEAR:
            default:
                $date->modify('first day of next year');
                break;
        }
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

    private static function calculateRpc(int $revenue, int $clicks): int
    {
        return (0 === $clicks) ? 0 : (int)round($revenue / $clicks);
    }

    private static function calculateRpm(int $revenue, int $views): int
    {
        return (0 === $views) ? 0 : (int)round($revenue / $views * 1000);
    }

    private static function calculateCtr(int $clicks, int $views): float
    {
        return (0 === $views) ? 0 : $clicks / $views;
    }

    private static function calculateInvalidRate(int $totalCount, int $validCount): float
    {
        return (0 === $totalCount) ? 0 : ($totalCount - $validCount) / $totalCount;
    }

    private function getDataEntriesWithoutEvents(array $queryResult, array $allZones, ?string $publisherId): array
    {
        $result = [];

        foreach ($allZones as $zone) {
            $zoneId = $zone->zone_id;
            $isZonePresent = false;

            foreach ($queryResult as $row) {
                if ($row->zone_id === $zoneId) {
                    $isZonePresent = true;
                    break;
                }
            }

            if (!$isZonePresent) {
                $calculation =
                    new ReportCalculation(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, self::PLACEHOLDER_FOR_EMPTY_DOMAIN);
                if (null === $publisherId) {
                    $selectedPublisherId = $zone->user_id;
                    $selectedPublisherEmail = $zone->user_email;
                } else {
                    $selectedPublisherId = null;
                    $selectedPublisherEmail = null;
                }
                $result[] =
                    new DataEntry(
                        $calculation,
                        $zone->site_id,
                        $zone->site_name,
                        $zoneId,
                        $zone->zone_name,
                        $selectedPublisherId,
                        $selectedPublisherEmail
                    );
            }
        }

        return $result;
    }

    private function fetchAllZonesWithSiteAndUser(?string $publisherId, ?string $siteId): array
    {
        $query = <<<SQL
SELECT u.id    AS user_id,
       u.email AS user_email,
       s.id    AS site_id,
       s.name  AS site_name,
       z.id    AS zone_id,
       z.name  AS zone_name
FROM users u
         JOIN sites s ON u.id = s.user_id
         JOIN zones z ON z.site_id = s.id
WHERE s.deleted_at IS NULL
SQL;

        if (null !== $publisherId) {
            $query .= sprintf(' AND u.uuid = 0x%s', $publisherId);
        }
        if (null !== $siteId) {
            $query .= sprintf(' AND s.uuid = 0x%s', $siteId);
        }

        return DB::select($query);
    }

    private function fetchStatsAggregates(
        ?string $publisherId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId
    ): array {
        $queryBuilder =
            (new MySqlAggregatedStatsQueryBuilder(StatsRepository::TYPE_STATS))
                ->setDateRange($dateStart, $dateEnd)
                ->appendSiteIdGroupBy();

        if (null !== $publisherId) {
            $queryBuilder->setPublisherId($publisherId);
        } else {
            $queryBuilder->appendPublisherIdGroupBy();
        }

        if ($siteId) {
            $queryBuilder->appendSiteIdWhereClause($siteId)->appendZoneIdGroupBy();
        } else {
            $queryBuilder->appendAnyZoneId();
        }

        $query = $queryBuilder->build();

        return $this->executeQuery($query, $dateStart);
    }

    private function fetchStatsLive(
        ?string $publisherId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId
    ): array {
        $queryBuilder =
            (new MySqlLiveStatsQueryBuilder(StatsRepository::TYPE_STATS))
                ->setDateRange($dateStart, $dateEnd)
                ->appendSiteIdGroupBy();

        if (null !== $publisherId) {
            $queryBuilder->setPublisherId($publisherId);
        } else {
            $queryBuilder->appendPublisherIdGroupBy();
        }

        if ($siteId) {
            $queryBuilder->appendSiteIdWhereClause($siteId)->appendZoneIdGroupBy();
        }

        $query = $queryBuilder->build();

        return $this->executeQuery($query, $dateStart);
    }

    private function fetchStatsTotalAggregates(
        ?string $publisherId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId
    ): array {
        $queryBuilder =
            (new MySqlAggregatedStatsQueryBuilder(StatsRepository::TYPE_STATS))
                ->setDateRange($dateStart, $dateEnd);

        if (null !== $publisherId) {
            $queryBuilder->setPublisherId($publisherId);
        }

        if ($siteId) {
            $queryBuilder->appendSiteIdWhereClause($siteId)->appendSiteIdGroupBy();
        }
        $queryBuilder->appendAnyZoneId();

        $query = $queryBuilder->build();

        return $this->executeQuery($query, $dateStart);
    }

    private function fetchStatsTotalLive(
        ?string $publisherId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId
    ): array {
        $queryBuilder =
            (new MySqlLiveStatsQueryBuilder(StatsRepository::TYPE_STATS))
                ->setDateRange($dateStart, $dateEnd);

        if (null !== $publisherId) {
            $queryBuilder->setPublisherId($publisherId);
        }

        if ($siteId) {
            $queryBuilder->appendSiteIdWhereClause($siteId)->appendSiteIdGroupBy();
        }

        $query = $queryBuilder->build();

        return $this->executeQuery($query, $dateStart);
    }

    private function getDateThresholdForLiveData(DateTimeZone $dateTimeZone): DateTime
    {
        return DateUtils::getDateTimeRoundedToCurrentHour(new DateTime('now', $dateTimeZone))->modify('-1 hour');
    }
}
