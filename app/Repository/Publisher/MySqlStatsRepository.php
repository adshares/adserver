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
use Adshares\Publisher\Dto\Result\Stats\Total;
use Adshares\Publisher\Repository\StatsRepository;
use DateTime;
use DateTimeZone;

class MySqlStatsRepository implements StatsRepository
{
    public function fetchView(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::VIEW_TYPE,
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
            StatsRepository::CLICK_TYPE,
            $publisherId,
            $resolution,
            $dateStart,
            $dateEnd,
            $siteId
        );

        return new ChartResult($result);
    }

    public function fetchRpc(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::RPC_TYPE,
            $publisherId,
            $resolution,
            $dateStart,
            $dateEnd,
            $siteId
        );

        return new ChartResult($result);
    }

    public function fetchRpm(
        string $publisherId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::RPM_TYPE,
            $publisherId,
            $resolution,
            $dateStart,
            $dateEnd,
            $siteId
        );

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
            StatsRepository::SUM_TYPE,
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
        $result = $this->fetch(
            StatsRepository::CTR_TYPE,
            $publisherId,
            $resolution,
            $dateStart,
            $dateEnd,
            $siteId
        );

        foreach ($result as &$row) {
            $row[1] = (float)$row[1];
        }

        return new ChartResult($result);
    }

    public function fetchStats(
        string $publisherId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): DataCollection {
        $queryBuilder = (new MySqlStatsQueryBuilder(StatsRepository::STATS_TYPE))
            ->setPublisherId($publisherId)
            ->setDateRange($dateStart, $dateEnd)
            ->appendSiteIdGroupBy();

        if ($siteId) {
            $queryBuilder
                ->appendSiteIdWhereClause($siteId)
                ->appendZoneIdGroupBy();
        }

        $query = $queryBuilder->build();
        $queryResult = $this->executeQuery($query, $dateStart);

        $result = [];
        foreach ($queryResult as $row) {
            $calculation = new Calculation(
                (int)$row->clicks,
                (int)$row->views,
                (float)$row->ctr,
                (float)$row->rpc,
                (float)$row->rpm,
                (int)$row->revenue
            );

            $zoneId = ($siteId !== null) ? bin2hex($row->zone_id) : null;
            $result[] = new DataEntry($calculation, bin2hex($row->site_id), $zoneId);
        }

        return new DataCollection($result);
    }

    public function fetchStatsTotal(
        string $publisherId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): Total {
        $queryBuilder = (new MySqlStatsQueryBuilder(StatsRepository::STATS_SUM_TYPE))
                ->setPublisherId($publisherId)
                ->setDateRange($dateStart, $dateEnd);

        if ($siteId) {
            $queryBuilder
                ->appendSiteIdWhereClause($siteId)
                ->appendSiteIdGroupBy();
        }

        $query = $queryBuilder->build();
        $queryResult = $this->executeQuery($query, $dateStart);

        if (!empty($queryResult)) {
            $row = $queryResult[0];
            $calculation = new Calculation(
                (int)$row->clicks,
                (int)$row->views,
                (float)$row->ctr,
                (float)$row->rpc,
                (float)$row->rpm,
                (int)$row->revenue
            );
        } else {
            $calculation = new Calculation(0, 0, 0, 0, 0, 0);
        }

        return new Total($calculation, $siteId);
    }

    public function fetchStatsToReport(
        string $publisherId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $siteId = null
    ): DataCollection {
        $queryBuilder = (new MySqlStatsQueryBuilder(StatsRepository::STATS_TYPE))
            ->setPublisherId($publisherId)
            ->setDateRange($dateStart, $dateEnd)
            ->appendDomainGroupBy()
            ->appendSiteIdGroupBy()
            ->appendZoneIdGroupBy();

        if ($siteId) {
            $queryBuilder->appendSiteIdWhereClause($siteId);
        }

        $query = $queryBuilder->build();

        $queryResult = $this->executeQuery($query, $dateStart);

        $result = [];
        foreach ($queryResult as $row) {
            $calculation = new Calculation(
                (int)$row->clicks,
                (int)$row->views,
                (float)$row->ctr,
                (float)$row->rpc,
                (float)$row->rpm,
                (int)$row->revenue,
                $row->domain
            );

            $zoneId = ($row->zone_id !== null) ? bin2hex($row->zone_id) : null;
            $result[] = new DataEntry($calculation, bin2hex($row->site_id), $zoneId);
        }

        return new DataCollection($result);
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
        $queryBuilder = (new MySqlStatsQueryBuilder($type))
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
        $dateTimeZone = $dateStart->getTimezone();
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
        if ($resolution !== StatsRepository::HOUR_RESOLUTION) {
            $date->setTime(0, 0, 0, 0);
        }

        foreach ($result as $row) {
            if ($resolution === StatsRepository::HOUR_RESOLUTION) {
                $date->setTime($row->h, 0, 0, 0);
            }

            switch ($resolution) {
                case StatsRepository::HOUR_RESOLUTION:
                case StatsRepository::DAY_RESOLUTION:
                    $date->setDate($row->y, $row->m, $row->d);
                    break;
                case StatsRepository::WEEK_RESOLUTION:
                    $yearweek = (string)$row->yw;
                    $year = (int)substr($yearweek, 0, 4);
                    $week = (int)substr($yearweek, 4);
                    $date->setISODate($year, $week, 1);
                    break;
                case StatsRepository::MONTH_RESOLUTION:
                    $date->setDate($row->y, $row->m, 1);
                    break;
                case StatsRepository::QUARTER_RESOLUTION:
                    $month = $row->q * 3 - 2;
                    $date->setDate($row->y, $month, 1);
                    break;
                case StatsRepository::YEAR_RESOLUTION:
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

        if ($resolution === StatsRepository::HOUR_RESOLUTION) {
            $date->setTime((int)$date->format('H'), 0, 0, 0);
        } else {
            $date->setTime(0, 0, 0, 0);
        }

        switch ($resolution) {
            case StatsRepository::HOUR_RESOLUTION:
            case StatsRepository::DAY_RESOLUTION:
                break;
            case StatsRepository::WEEK_RESOLUTION:
                $date->setISODate((int)$date->format('Y'), (int)$date->format('W'), 1);
                break;
            case StatsRepository::MONTH_RESOLUTION:
                $date->setDate((int)$date->format('Y'), (int)$date->format('m'), 1);
                break;
            case StatsRepository::QUARTER_RESOLUTION:
                $quarter = (int)floor((int)$date->format('m') - 1 / 3);
                $month = $quarter * 3 + 1;
                $date->setDate((int)$date->format('Y'), $month, 1);
                break;
            case StatsRepository::YEAR_RESOLUTION:
            default:
                $date->setDate((int)$date->format('Y'), 1, 1);
                break;
        }

        return $date;
    }

    private static function advanceDateTime(string $resolution, DateTime $date): void
    {
        switch ($resolution) {
            case StatsRepository::HOUR_RESOLUTION:
                $date->modify('+1 hour');
                break;
            case StatsRepository::DAY_RESOLUTION:
                $date->modify('tomorrow');
                break;
            case StatsRepository::WEEK_RESOLUTION:
                $date->modify('+7 days');
                break;
            case StatsRepository::MONTH_RESOLUTION:
                $date->modify('first day of next month');
                break;
            case StatsRepository::QUARTER_RESOLUTION:
                $date->modify('first day of next month');
                $date->modify('first day of next month');
                $date->modify('first day of next month');
                break;
            case StatsRepository::YEAR_RESOLUTION:
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
}
