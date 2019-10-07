<?php
/**
 * Copyright (c) 2018 Adshares sp. z o.o.
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

namespace Adshares\Adserver\Repository\Publisher;

use Adshares\Adserver\Facades\DB;
use Adshares\Publisher\Dto\Result\ChartResult;
use Adshares\Publisher\Dto\Result\Stats\Calculation;
use Adshares\Publisher\Dto\Result\Stats\DataCollection;
use Adshares\Publisher\Dto\Result\Stats\DataEntry;
use Adshares\Publisher\Dto\Result\Stats\ReportCalculation;
use Adshares\Publisher\Dto\Result\Stats\Total;
use Adshares\Publisher\Repository\StatsRepository;
use DateTime;
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
                $this->calculateInvalidRate((int)$resultViewsAll[$i][1], (int)$resultViews[$i][1]),
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
                $this->calculateInvalidRate((int)$resultClicksAll[$i][1], (int)$resultClicks[$i][1]),
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
                $this->calculateRpc((int)$resultSum[$i][1], (int)$resultClicks[$i][1]),
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
                $this->calculateRpm((int)$resultSum[$i][1], (int)$resultViews[$i][1]),
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
                $this->calculateCtr((int)$resultClicks[$i][1], (int)$resultViews[$i][1]),
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
        $queryBuilder = (new MySqlAggregatedStatsQueryBuilder(StatsRepository::TYPE_STATS))
            ->setDateRange($dateStart, $dateEnd)
            ->appendSiteIdGroupBy();

        if (null !== $publisherId) {
            $queryBuilder->setPublisherId($publisherId);
        } else {
            $queryBuilder->appendPublisherIdGroupBy();
        }

        if ($siteId) {
            $queryBuilder
                ->appendSiteIdWhereClause($siteId)
                ->appendZoneIdGroupBy();
        }

        $query = $queryBuilder->build();
        $queryResult = $this->executeQuery($query, $dateStart);

        $result = [];
        foreach ($queryResult as $row) {
            $clicks = (int)$row->clicks;
            $views = (int)$row->views;
            $revenue = (int)$row->revenue;

            $calculation = new Calculation(
                $clicks,
                $views,
                $this->calculateCtr($clicks, $views),
                $this->calculateRpc($revenue, $clicks),
                $this->calculateRpm($revenue, $views),
                $revenue
            );

            $zoneId = ($siteId !== null) ? bin2hex($row->zone_id) : null;
            $selectedPublisherId = ($publisherId === null) ? bin2hex($row->publisher_id) : null;
            $result[] = new DataEntry($calculation, bin2hex($row->site_id), $zoneId, $selectedPublisherId);
        }

        return new DataCollection($result);
    }

    public function fetchStatsTotal(
        ?string $publisherId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): Total {
        $queryBuilder = (new MySqlAggregatedStatsQueryBuilder(StatsRepository::TYPE_STATS))
            ->setDateRange($dateStart, $dateEnd);

        if (null !== $publisherId) {
            $queryBuilder->setPublisherId($publisherId);
        }

        if ($siteId) {
            $queryBuilder
                ->appendSiteIdWhereClause($siteId)
                ->appendSiteIdGroupBy();
        }

        $query = $queryBuilder->build();
        $queryResult = $this->executeQuery($query, $dateStart);

        if (!empty($queryResult)) {
            $row = $queryResult[0];
            $clicks = (int)$row->clicks;
            $views = (int)$row->views;
            $revenue = (int)$row->revenue;

            $calculation = new Calculation(
                $clicks,
                $views,
                $this->calculateCtr($clicks, $views),
                $this->calculateRpc($revenue, $clicks),
                $this->calculateRpm($revenue, $views),
                $revenue
            );
        } else {
            $calculation = new Calculation(0, 0, 0, 0, 0, 0);
        }

        return new Total($calculation, $siteId);
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
                $this->calculateInvalidRate($clicksAll, $clicks),
                $views,
                $viewsAll,
                $this->calculateInvalidRate($viewsAll, $views),
                (int)$row->viewsUnique,
                $this->calculateCtr($clicks, $views),
                $this->calculateRpc($revenue, $clicks),
                $this->calculateRpm($revenue, $views),
                $revenue,
                $row->domain ?: self::PLACEHOLDER_FOR_EMPTY_DOMAIN
            );

            $zoneId = ($row->zone_id !== null) ? bin2hex($row->zone_id) : null;
            $selectedPublisherId = ($publisherId === null) ? bin2hex($row->publisher_id) : null;
            $result[] = new DataEntry($calculation, bin2hex($row->site_id), $zoneId, $selectedPublisherId);
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
        $queryBuilder = (new MySqlAggregatedStatsQueryBuilder($type))
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
        $queryResult = $this->executeQuery($query, $dateStart);

        $result = $this->processQueryResult($resolution, $dateStart, $dateEnd, $queryResult);

        return $result;
    }

    private function executeQuery(string $query, DateTime $dateStart): array
    {
        $dateTimeZone = new DateTimeZone($dateStart->format('O'));
        $this->setDbSessionTimezone($dateTimeZone);
        $queryResult = DB::select($query);
        $this->unsetDbSessionTimeZone();

        return $queryResult;
    }

    private function setDbSessionTimezone(DateTimeZone $dateTimeZone): void
    {
        DB::statement('SET @tmp_time_zone = (SELECT @@session.time_zone)');
        DB::statement(sprintf("SET time_zone = '%s'", $dateTimeZone->getName()));
    }

    private function unsetDbSessionTimeZone(): void
    {
        DB::statement('SET time_zone = (SELECT @tmp_time_zone)');
    }

    private function processQueryResult(
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        array $queryResult
    ): array {
        $dateTimeZone = $dateStart->getTimezone();
        $concatenatedResult = self::concatenateDateColumns($dateTimeZone, $queryResult, $resolution);
        $emptyResult = self::createEmptyResult($dateTimeZone, $resolution, $dateStart, $dateEnd);
        $joinedResult = self::joinResultWithEmpty($concatenatedResult, $emptyResult);

        $result = $this->mapResult($joinedResult);
        $result = $this->overwriteStartDate($dateStart, $result);

        return $result;
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

            $d = $date->format(DateTime::ATOM);
            $formattedResult[$d] = $row->c;
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
            $dates[] = $date->format(DateTime::ATOM);
            self::advanceDateTime($resolution, $date);
        }

        if (empty($dates)) {
            $dates[] = $date->format(DateTime::ATOM);
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

    private function mapResult(array $joinedResult): array
    {
        $result = [];
        foreach ($joinedResult as $key => $value) {
            $result[] = [$key, $value];
        }

        return $result;
    }

    private function overwriteStartDate(DateTime $dateStart, array $result): array
    {
        if (count($result) > 0) {
            $result[0][0] = $dateStart->format(DateTime::ATOM);
        }

        return $result;
    }

    private function calculateRpc(int $revenue, int $clicks): int
    {
        return (0 === $clicks) ? 0 : (int)round($revenue / $clicks);
    }

    private function calculateRpm(int $revenue, int $views): int
    {
        return (0 === $views) ? 0 : (int)round($revenue / $views * 1000);
    }

    private function calculateCtr(int $clicks, int $views): float
    {
        return (0 === $views) ? 0 : $clicks / $views;
    }

    private function calculateInvalidRate(int $totalCount, int $validCount): float
    {
        return (0 === $totalCount) ? 0 : ($totalCount - $validCount) / $totalCount;
    }

    private function getDataEntriesWithoutEvents(array $queryResult, array $allZones, ?string $publisherId): array
    {
        $result = [];

        foreach ($allZones as $zone) {
            $binaryZoneId = $zone->zone_id;
            $isZonePresent = false;

            foreach ($queryResult as $row) {
                if ($row->zone_id === $binaryZoneId) {
                    $isZonePresent = true;
                    break;
                }
            }

            if (!$isZonePresent) {
                $calculation =
                    new ReportCalculation(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, self::PLACEHOLDER_FOR_EMPTY_DOMAIN);
                $selectedPublisherId = ($publisherId === null) ? bin2hex($zone->user_id) : null;
                $result[] =
                    new DataEntry(
                        $calculation,
                        bin2hex($zone->site_id),
                        bin2hex($binaryZoneId),
                        $selectedPublisherId
                    );
            }
        }

        return $result;
    }

    private function fetchAllZonesWithSiteAndUser(?string $publisherId, ?string $siteId): array
    {
        $query = <<<SQL
SELECT u.uuid AS user_id, s.uuid AS site_id, z.uuid AS zone_id
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
}
