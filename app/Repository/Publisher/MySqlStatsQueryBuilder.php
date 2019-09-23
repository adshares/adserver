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

use Adshares\Adserver\Models\NetworkEventLog;
use Adshares\Adserver\Repository\Common\MySqlQueryBuilder;
use Adshares\Publisher\Repository\StatsRepository;
use DateTime;
use function sprintf;

class MySqlStatsQueryBuilder extends MySqlQueryBuilder
{
    protected const TABLE_NAME = 'network_event_logs e';

    private const ALLOWED_TYPES = [
        StatsRepository::TYPE_VIEW,
        StatsRepository::TYPE_VIEW_UNIQUE,
        StatsRepository::TYPE_VIEW_ALL,
        StatsRepository::TYPE_VIEW_INVALID_RATE,
        StatsRepository::TYPE_CLICK,
        StatsRepository::TYPE_CLICK_ALL,
        StatsRepository::TYPE_CLICK_INVALID_RATE,
        StatsRepository::TYPE_REVENUE_BY_CASE,
        StatsRepository::TYPE_CTR,
        StatsRepository::TYPE_STATS,
        StatsRepository::TYPE_STATS_REPORT,
    ];

    public function __construct(string $type)
    {
        $this->selectBaseColumns($type);
        $this->appendEventType($type);

        parent::__construct($type);
    }

    protected function isTypeAllowed(string $type): bool
    {
        return in_array($type, self::ALLOWED_TYPES, true);
    }

    protected function getTableName(): string
    {
        return self::TABLE_NAME;
    }

    private function selectBaseColumns(string $type): void
    {
        switch ($type) {
            case StatsRepository::TYPE_VIEW:
            case StatsRepository::TYPE_VIEW_ALL:
            case StatsRepository::TYPE_CLICK:
            case StatsRepository::TYPE_CLICK_ALL:
                $this->column('COUNT(1) AS c');
                break;
            case StatsRepository::TYPE_VIEW_UNIQUE:
                $this->column('COUNT(DISTINCT IFNULL(e.user_id,e.tracking_id), e.site_id) AS c');
                break;
            case StatsRepository::TYPE_VIEW_INVALID_RATE:
            case StatsRepository::TYPE_CLICK_INVALID_RATE:
                $this->column('COALESCE(AVG(IF(e.paid_amount_currency IS NULL, 1, 0)), 0) AS c');
                break;
            case StatsRepository::TYPE_REVENUE_BY_CASE:
                $this->column('COALESCE(SUM(e.paid_amount_currency), 0) AS c');
                break;
            case StatsRepository::TYPE_CTR:
                $this->column('COALESCE(AVG(IF(e.is_view_clicked, 1, 0)), 0) AS c');
                break;
            case StatsRepository::TYPE_STATS:
                $this->selectBaseStatsColumns();
                break;
            case StatsRepository::TYPE_STATS_REPORT:
                $this->selectBaseStatsReportColumns();
                break;
        }
    }

    private function appendEventType(string $type): void
    {
        switch ($type) {
            case StatsRepository::TYPE_VIEW:
            case StatsRepository::TYPE_VIEW_UNIQUE:
            case StatsRepository::TYPE_CTR:
                $this->where(sprintf("e.event_type = '%s'", NetworkEventLog::TYPE_VIEW));
                $this->where('e.paid_amount_currency IS NOT NULL');
                break;
            case StatsRepository::TYPE_VIEW_ALL:
            case StatsRepository::TYPE_VIEW_INVALID_RATE:
                $this->where(sprintf("e.event_type = '%s'", NetworkEventLog::TYPE_VIEW));
                break;
            case StatsRepository::TYPE_CLICK:
                $this->where(sprintf("e.event_type = '%s'", NetworkEventLog::TYPE_VIEW));
                $this->where('e.is_view_clicked = 1');
                $this->where('e.paid_amount_currency IS NOT NULL');
                break;
            case StatsRepository::TYPE_CLICK_ALL:
            case StatsRepository::TYPE_CLICK_INVALID_RATE:
                $this->where(sprintf("e.event_type = '%s'", NetworkEventLog::TYPE_VIEW));
                $this->where('e.is_view_clicked = 1');
                break;
        }
    }

    private function withoutRemovedSites(): void
    {
        $this->join('sites s', 's.uuid = e.site_id');
        $this->where('s.deleted_at is null');
    }

    private function selectBaseStatsColumns(): void
    {
        $filterEventValid = 'AND e.paid_amount_currency IS NOT NULL';

        $this->column(
            sprintf(
                "SUM(IF(e.event_type = '%s' AND e.is_view_clicked = 1 %s, 1, 0)) AS clicks",
                NetworkEventLog::TYPE_VIEW,
                $filterEventValid
            )
        );
        $this->column(
            sprintf("SUM(IF(e.event_type = '%s' %s, 1, 0)) AS views", NetworkEventLog::TYPE_VIEW, $filterEventValid)
        );
        $this->column(
            sprintf(
                "SUM(IF(e.event_type IN ('%s', '%s') %s, e.paid_amount_currency, 0)) AS revenue",
                NetworkEventLog::TYPE_CLICK,
                NetworkEventLog::TYPE_VIEW,
                $filterEventValid
            )
        );
    }

    private function selectBaseStatsReportColumns(): void
    {
        $this->selectBaseStatsColumns();

        $this->column(
            sprintf(
                "SUM(IF(e.event_type = '%s' AND e.is_view_clicked = 1, 1, 0)) AS clicksAll",
                NetworkEventLog::TYPE_VIEW
            )
        );
        $this->column(
            sprintf("SUM(IF(e.event_type = '%s', 1, 0)) AS viewsAll", NetworkEventLog::TYPE_VIEW)
        );
        $this->column(
            sprintf(
                "COUNT(DISTINCT(CASE WHEN e.event_type = '%s' AND e.paid_amount_currency IS NOT NULL"
                .' THEN IFNULL(e.user_id, e.tracking_id) END)) AS viewsUnique',
                NetworkEventLog::TYPE_VIEW
            )
        );
    }

    public function setPublisherId(string $publisherId): self
    {
        $this->where(sprintf('e.publisher_id = 0x%s', $publisherId));

        return $this;
    }

    public function appendPublisherIdGroupBy(): self
    {
        $this->column('e.publisher_id AS publisher_id');
        $this->groupBy('e.publisher_id');

        return $this;
    }

    public function setDateRange(DateTime $dateStart, DateTime $dateEnd): self
    {
        $this->where(
            sprintf(
                'e.created_at BETWEEN \'%s\' AND \'%s\'',
                $this->convertDateTimeToMySqlDate($dateStart),
                $this->convertDateTimeToMySqlDate($dateEnd)
            )
        );

        return $this;
    }

    public function selectDateStartColumn(DateTime $dateStart): self
    {
        $this->column(sprintf("'%s' AS start_date", $this->convertDateTimeToMySqlDate($dateStart)));

        return $this;
    }

    public function appendResolution(string $resolution): self
    {
        switch ($resolution) {
            case StatsRepository::RESOLUTION_HOUR:
                $this->column('YEAR(e.created_at) AS y');
                $this->column('MONTH(e.created_at) as m');
                $this->column('DAY(e.created_at) AS d');
                $this->column('HOUR(e.created_at) AS h');
                $this->groupBy('YEAR(e.created_at)');
                $this->groupBy('MONTH(e.created_at)');
                $this->groupBy('DAY(e.created_at)');
                $this->groupBy('HOUR(e.created_at)');
                break;
            case StatsRepository::RESOLUTION_DAY:
                $this->column('YEAR(e.created_at) AS y');
                $this->column('MONTH(e.created_at) as m');
                $this->column('DAY(e.created_at) AS d');

                $this->groupBy('YEAR(e.created_at)');
                $this->groupBy('MONTH(e.created_at)');
                $this->groupBy('DAY(e.created_at)');
                break;
            case StatsRepository::RESOLUTION_WEEK:
                $this->column('YEARWEEK(e.created_at, 3) as yw');
                $this->groupBy('YEARWEEK(e.created_at, 3)');
                break;
            case StatsRepository::RESOLUTION_MONTH:
                $this->column('YEAR(e.created_at) AS y');
                $this->column('MONTH(e.created_at) as m');
                $this->groupBy('YEAR(e.created_at)');
                $this->groupBy('MONTH(e.created_at)');
                break;
            case StatsRepository::RESOLUTION_QUARTER:
                $this->column('YEAR(e.created_at) AS y');
                $this->column('QUARTER(e.created_at) as q');
                $this->groupBy('YEAR(e.created_at)');
                $this->groupBy('QUARTER(e.created_at)');
                break;
            case StatsRepository::RESOLUTION_YEAR:
            default:
                $this->column('YEAR(e.created_at) AS y');
                $this->groupBy('YEAR(e.created_at)');
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

    public function appendZoneIdGroupBy(): self
    {
        $this->column('e.zone_id AS zone_id');
        $this->groupBy('e.zone_id');

        return $this;
    }

    public function appendSiteIdGroupBy(): self
    {
        $this->column('e.site_id AS site_id');
        $this->groupBy('e.site_id');
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
