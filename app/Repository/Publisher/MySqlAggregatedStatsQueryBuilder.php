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

use Adshares\Adserver\Repository\Common\MySqlQueryBuilder;
use Adshares\Publisher\Repository\StatsRepository;
use DateTimeInterface;

use function in_array;
use function sprintf;

class MySqlAggregatedStatsQueryBuilder extends MySqlQueryBuilder
{
    private const ALLOWED_TYPES = [
        StatsRepository::TYPE_VIEW,
        StatsRepository::TYPE_VIEW_UNIQUE,
        StatsRepository::TYPE_VIEW_ALL,
        StatsRepository::TYPE_CLICK,
        StatsRepository::TYPE_CLICK_ALL,
        StatsRepository::TYPE_REVENUE_BY_CASE,
        StatsRepository::TYPE_REVENUE_BY_HOUR,
        StatsRepository::TYPE_STATS,
        StatsRepository::TYPE_STATS_REPORT,
    ];

    public function __construct(string $type)
    {
        $this->selectBaseColumns($type);
        $this->withoutRemovedSites();

        parent::__construct($type);
    }

    protected function isTypeAllowed(string $type): bool
    {
        return in_array($type, self::ALLOWED_TYPES, true);
    }

    protected function getTableName(): string
    {
        return (StatsRepository::TYPE_STATS_REPORT === $this->getType())
            ? 'network_case_logs_hourly e' : 'network_case_logs_hourly_stats e';
    }

    private function selectBaseColumns(string $type): void
    {
        switch ($type) {
            case StatsRepository::TYPE_VIEW:
                $this->column('SUM(e.views) AS c');
                break;
            case StatsRepository::TYPE_VIEW_ALL:
                $this->column('SUM(e.views_all) AS c');
                break;
            case StatsRepository::TYPE_CLICK:
                $this->column('SUM(e.clicks) AS c');
                break;
            case StatsRepository::TYPE_CLICK_ALL:
                $this->column('SUM(e.clicks_all) AS c');
                break;
            case StatsRepository::TYPE_VIEW_UNIQUE:
                $this->column('SUM(e.views_unique) AS c');
                break;
            case StatsRepository::TYPE_REVENUE_BY_CASE:
                $this->column('SUM(e.revenue_case) AS c');
                break;
            case StatsRepository::TYPE_REVENUE_BY_HOUR:
                $this->column('SUM(e.revenue_hour) AS c');
                break;
            case StatsRepository::TYPE_STATS:
                $this->selectBaseStatsColumns();
                break;
            case StatsRepository::TYPE_STATS_REPORT:
                $this->selectBaseStatsReportColumns();
                break;
        }
    }

    private function selectBaseStatsColumns(): void
    {
        $this->column('SUM(e.clicks) AS clicks');
        $this->column('SUM(e.views) AS views');
        $this->column('SUM(e.revenue_case) AS revenue');
    }

    private function selectBaseStatsReportColumns(): void
    {
        $this->selectBaseStatsColumns();

        $this->column('SUM(e.clicks_all) AS clicksAll');
        $this->column('SUM(e.views_all) AS viewsAll');
        $this->column('SUM(e.views_unique) AS viewsUnique');
    }

    private function withoutRemovedSites(): void
    {
        $this->join('sites s', 's.uuid = e.site_id');
        $this->where('s.deleted_at IS NULL');
    }

    public function setPublisherId(string $publisherId): self
    {
        $this->where(sprintf('e.publisher_id = 0x%s', $publisherId));

        return $this;
    }

    public function appendPublisherIdGroupBy(): self
    {
        $this->join('users', 'users.uuid = e.publisher_id');
        $this->column('users.id AS publisher_id');
        $this->column('users.email AS publisher_email');
        $this->groupBy('users.uuid');

        return $this;
    }

    public function setDateRange(DateTimeInterface $dateStart, DateTimeInterface $dateEnd): self
    {
        $this->where(
            sprintf(
                "e.hour_timestamp BETWEEN '%s' AND '%s'",
                $this->convertDateTimeToMySqlDate($dateStart),
                $this->convertDateTimeToMySqlDate($dateEnd)
            )
        );

        return $this;
    }

    public function appendResolution(string $resolution): self
    {
        switch ($resolution) {
            case StatsRepository::RESOLUTION_HOUR:
                $this->column('YEAR(e.hour_timestamp) AS y');
                $this->column('MONTH(e.hour_timestamp) as m');
                $this->column('DAY(e.hour_timestamp) AS d');
                $this->column('HOUR(e.hour_timestamp) AS h');
                $this->groupBy('YEAR(e.hour_timestamp)');
                $this->groupBy('MONTH(e.hour_timestamp)');
                $this->groupBy('DAY(e.hour_timestamp)');
                $this->groupBy('HOUR(e.hour_timestamp)');
                break;
            case StatsRepository::RESOLUTION_DAY:
                $this->column('YEAR(e.hour_timestamp) AS y');
                $this->column('MONTH(e.hour_timestamp) as m');
                $this->column('DAY(e.hour_timestamp) AS d');

                $this->groupBy('YEAR(e.hour_timestamp)');
                $this->groupBy('MONTH(e.hour_timestamp)');
                $this->groupBy('DAY(e.hour_timestamp)');
                break;
            case StatsRepository::RESOLUTION_WEEK:
                $this->column('YEARWEEK(e.hour_timestamp, 3) as yw');
                $this->groupBy('YEARWEEK(e.hour_timestamp, 3)');
                break;
            case StatsRepository::RESOLUTION_MONTH:
                $this->column('YEAR(e.hour_timestamp) AS y');
                $this->column('MONTH(e.hour_timestamp) as m');
                $this->groupBy('YEAR(e.hour_timestamp)');
                $this->groupBy('MONTH(e.hour_timestamp)');
                break;
            case StatsRepository::RESOLUTION_QUARTER:
                $this->column('YEAR(e.hour_timestamp) AS y');
                $this->column('QUARTER(e.hour_timestamp) as q');
                $this->groupBy('YEAR(e.hour_timestamp)');
                $this->groupBy('QUARTER(e.hour_timestamp)');
                break;
            case StatsRepository::RESOLUTION_YEAR:
            default:
                $this->column('YEAR(e.hour_timestamp) AS y');
                $this->groupBy('YEAR(e.hour_timestamp)');
                break;
        }

        return $this;
    }

    public function appendSiteIdWhereClause(string $siteId): self
    {
        $this->where(sprintf('e.site_id = 0x%s', $siteId));

        return $this;
    }

    public function appendZoneIdWhereClause(string $zoneId): self
    {
        $this->where(sprintf('e.zone_id = 0x%s', $zoneId));

        return $this;
    }

    public function appendAnyZoneId(): self
    {
        $this->where('e.zone_id IS NULL');

        return $this;
    }

    public function appendZoneIdGroupBy(): self
    {
        $this->join('zones z', 'z.uuid = e.zone_id');
        $this->column('z.id AS zone_id');
        $this->column('z.name AS zone_name');
        $this->where('e.zone_id IS NOT NULL');
        $this->groupBy('z.id');

        return $this;
    }

    public function appendSiteIdGroupBy(): self
    {
        $this->column('s.id AS site_id');
        $this->column('s.name AS site_name');
        $this->groupBy('s.id');
        $this->having('clicks>0');
        $this->having('views>0');
        $this->having('revenue>0');

        if (StatsRepository::TYPE_STATS_REPORT === $this->getType()) {
            $this->having('clicksAll>0');
            $this->having('viewsAll>0');
            $this->having('viewsUnique>0');
        }

        return $this;
    }

    public function appendDomainGroupBy(): self
    {
        $this->column("IFNULL(e.domain, '') AS domain");
        $this->groupBy("IFNULL(e.domain, '')");

        return $this;
    }
}
