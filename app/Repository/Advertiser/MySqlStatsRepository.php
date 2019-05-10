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

namespace Adshares\Adserver\Repository\Advertiser;

use Adshares\Adserver\Facades\DB;
use Adshares\Advertiser\Dto\Result\ChartResult;
use Adshares\Advertiser\Dto\Result\Stats\Calculation;
use Adshares\Advertiser\Dto\Result\Stats\DataCollection;
use Adshares\Advertiser\Dto\Result\Stats\DataEntry;
use Adshares\Advertiser\Dto\Result\Stats\ReportCalculation;
use Adshares\Advertiser\Dto\Result\Stats\Total;
use Adshares\Advertiser\Repository\StatsRepository;
use function bin2hex;
use DateTime;
use DateTimeZone;

class MySqlStatsRepository implements StatsRepository
{
    private const PLACEHOLDER_FORM_EMPTY_DOMAIN = 'N/A';

    public function fetchView(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_VIEW,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId
        );

        return new ChartResult($result);
    }

    public function fetchViewAll(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_VIEW_ALL,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId
        );

        return new ChartResult($result);
    }

    public function fetchViewInvalidRate(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_VIEW_INVALID_RATE,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId
        );

        foreach ($result as &$row) {
            $row[1] = (float)$row[1];
        }

        return new ChartResult($result);
    }

    public function fetchViewUnique(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_VIEW_UNIQUE,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId
        );

        return new ChartResult($result);
    }

    public function fetchClick(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_CLICK,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId
        );

        return new ChartResult($result);
    }

    public function fetchClickAll(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_CLICK_ALL,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId
        );

        return new ChartResult($result);
    }

    public function fetchClickInvalidRate(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_CLICK_INVALID_RATE,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId
        );

        foreach ($result as &$row) {
            $row[1] = (float)$row[1];
        }

        return new ChartResult($result);
    }

    public function fetchCpc(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        $resultSum = $this->fetch(
            StatsRepository::TYPE_SUM,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId
        );

        $resultCount = $this->fetch(
            StatsRepository::TYPE_CLICK,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId
        );

        $result = [];
        
        $rowCount = count($resultCount);
        
        for ($i = 0; $i < $rowCount; $i++) {
            $result[] = [
                $resultCount[$i][0],
                $this->calculateCpc((int)$resultSum[$i][1], (int)$resultCount[$i][1]),
            ];
        }

        return new ChartResult($result);
    }

    public function fetchCpm(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        $resultSum = $this->fetch(
            StatsRepository::TYPE_SUM,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId
        );

        $resultCount = $this->fetch(
            StatsRepository::TYPE_VIEW,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId
        );

        $result = [];

        $rowCount = count($resultCount);

        for ($i = 0; $i < $rowCount; $i++) {
            $result[] = [
                $resultCount[$i][0],
                $this->calculateCpm((int)$resultSum[$i][1], (int)$resultCount[$i][1]),
            ];
        }

        return new ChartResult($result);
    }

    public function fetchSum(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_SUM,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId
        );

        return new ChartResult($result);
    }

    public function fetchCtr(
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): ChartResult {
        $result = $this->fetch(
            StatsRepository::TYPE_CTR,
            $advertiserId,
            $resolution,
            $dateStart,
            $dateEnd,
            $campaignId
        );

        foreach ($result as &$row) {
            $row[1] = (float)$row[1];
        }

        return new ChartResult($result);
    }

    public function fetchStats(
        string $advertiserId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): DataCollection {
        $queryBuilder = (new MySqlStatsQueryBuilder(StatsRepository::TYPE_STATS))
            ->setAdvertiserId($advertiserId)
            ->setDateRange($dateStart, $dateEnd)
            ->appendCampaignIdGroupBy();

        if ($campaignId) {
            $queryBuilder
                ->appendCampaignIdWhereClause($campaignId)
                ->appendBannerIdGroupBy();
        }

        $query = $queryBuilder->build();
        $queryResult = $this->executeQuery($query, $dateStart);

        $result = [];
        foreach ($queryResult as $row) {
            $clicks = (int)$row->clicks;
            $views = (int)$row->views;
            $cost = (int)$row->cost;

            $calculation = new Calculation(
                $clicks,
                $views,
                (float)$row->ctr,
                $this->calculateCpc($cost, $clicks),
                $this->calculateCpm($cost, $views),
                $cost
            );

            $bannerId = ($campaignId !== null) ? bin2hex($row->banner_id) : null;
            $result[] = new DataEntry($calculation, bin2hex($row->campaign_id), $bannerId);
        }

        return new DataCollection($result);
    }

    public function fetchStatsTotal(
        string $advertiserId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): Total {
        $queryBuilder = (new MySqlStatsQueryBuilder(StatsRepository::TYPE_STATS))
            ->setAdvertiserId($advertiserId)
            ->setDateRange($dateStart, $dateEnd);

        if ($campaignId) {
            $queryBuilder
                ->appendCampaignIdWhereClause($campaignId)
                ->appendCampaignIdGroupBy();
        }

        $query = $queryBuilder->build();
        $queryResult = $this->executeQuery($query, $dateStart);

        if (!empty($queryResult)) {
            $row = $queryResult[0];
            $clicks = (int)$row->clicks;
            $views = (int)$row->views;
            $cost = (int)$row->cost;

            $calculation = new Calculation(
                $clicks,
                $views,
                (float)$row->ctr,
                $this->calculateCpc($cost, $clicks),
                $this->calculateCpm($cost, $views),
                $cost
            );
        } else {
            $calculation = new Calculation(0, 0, 0, 0, 0, 0);
        }

        return new Total($calculation, $campaignId);
    }

    public function fetchStatsToReport(
        string $advertiserId,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId = null
    ): DataCollection {
        $queryBuilder = (new MySqlStatsQueryBuilder(StatsRepository::TYPE_STATS_REPORT))
            ->setAdvertiserId($advertiserId)
            ->setDateRange($dateStart, $dateEnd)
            ->appendDomainGroupBy()
            ->appendCampaignIdGroupBy()
            ->appendBannerIdGroupBy();

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
                $this->calculateInvalidRate($clicksAll, $clicks),
                $views,
                $viewsAll,
                $this->calculateInvalidRate($viewsAll, $views),
                (int)$row->viewsUnique,
                (float)$row->ctr,
                $this->calculateCpc($cost, $clicks),
                $this->calculateCpm($cost, $views),
                $cost,
                $row->domain ?: self::PLACEHOLDER_FORM_EMPTY_DOMAIN
            );

            $bannerId = ($row->banner_id !== null) ? bin2hex($row->banner_id) : null;
            $result[] = new DataEntry($calculation, bin2hex($row->campaign_id), $bannerId);
        }

        return new DataCollection($result);
    }

    private function fetch(
        string $type,
        string $advertiserId,
        string $resolution,
        DateTime $dateStart,
        DateTime $dateEnd,
        ?string $campaignId,
        ?string $bannerId = null
    ): array {
        $queryBuilder = (new MySqlStatsQueryBuilder($type))
            ->setAdvertiserId($advertiserId)
            ->setDateRange($dateStart, $dateEnd)
            ->appendResolution($resolution);

        if ($campaignId) {
            $queryBuilder->appendCampaignIdWhereClause($campaignId);
        }

        if ($bannerId) {
            $queryBuilder->appendBannerIdWhereClause($bannerId);
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

    private function calculateCpc(int $cost, int $clicks): int
    {
        return (0 === $clicks) ? 0 : (int)round($cost / $clicks);
    }

    private function calculateCpm(int $cost, int $views): int
    {
        return (0 === $views) ? 0 : (int)round($cost / $views * 1000);
    }

    private function calculateInvalidRate(int $totalCount, int $validCount): float
    {
        return (0 === $totalCount) ? 0 : ($totalCount - $validCount) / $totalCount;
    }
}
