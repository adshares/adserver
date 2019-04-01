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

use Adshares\Adserver\Models\EventLog;
use Adshares\Adserver\Repository\Common\MySqlQueryBuilder;
use Adshares\Publisher\Repository\StatsRepository;
use DateTime;
use function sprintf;

class MySqlStatsQueryBuilder extends MySqlQueryBuilder
{
    protected const TABLE_NAME = 'network_event_logs e';

    private const ALLOWED_TYPES = [
        StatsRepository::VIEW_TYPE,
        StatsRepository::CLICK_TYPE,
        StatsRepository::RPC_TYPE,
        StatsRepository::RPM_TYPE,
        StatsRepository::SUM_TYPE,
        StatsRepository::CTR_TYPE,
        StatsRepository::STATS_TYPE,
        StatsRepository::STATS_SUM_TYPE,
    ];

    public function __construct(string $type)
    {
        $this->selectBaseColumns($type);
        $this->appendEventType($type);

        if ($type === StatsRepository::STATS_TYPE || $type === StatsRepository::STATS_SUM_TYPE) {
            $this->selectBaseStatsColumns();
        }

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
            case StatsRepository::VIEW_TYPE:
            case StatsRepository::CLICK_TYPE:
                $this->column('COUNT(e.created_at) AS c');
                break;
            case StatsRepository::RPC_TYPE:
                $this->column('COALESCE(AVG(e.paid_amount), 0) AS c');
                break;
            case StatsRepository::RPM_TYPE:
                $this->column('COALESCE(AVG(e.paid_amount), 0)*1000 AS c');
                break;
            case StatsRepository::SUM_TYPE:
                $this->column('COALESCE(SUM(e.paid_amount), 0) AS c');
                break;
            case StatsRepository::CTR_TYPE:
                $this->column('COALESCE(AVG(IF(e.is_view_clicked, 1, 0)), 0) AS c');
                break;
        }
    }

    private function appendEventType(string $type): void
    {
        switch ($type) {
            case StatsRepository::VIEW_TYPE:
            case StatsRepository::RPM_TYPE:
            case StatsRepository::CTR_TYPE:
                $this->where(sprintf("e.event_type = '%s'", EventLog::TYPE_VIEW));
                break;
            case StatsRepository::CLICK_TYPE:
            case StatsRepository::RPC_TYPE:
                $this->where(sprintf("e.event_type = '%s'", EventLog::TYPE_VIEW));
                $this->where(sprintf('e.is_view_clicked = %d', 1));
                break;
        }
    }

    private function selectBaseStatsColumns(): void
    {
        $this->column('SUM(IF(e.event_type = \'view\' AND e.is_view_clicked = 1, 1, 0)) AS clicks');
        $this->column('SUM(IF(e.event_type = \'view\', 1, 0)) AS views');
        $this->column(
            'IFNULL(AVG(CASE WHEN (e.event_type <> \'view\') THEN NULL WHEN (e.is_view_clicked = 1) '
                    .'THEN 1 ELSE 0 END), 0) AS ctr'
        );
        $this->column('IFNULL(AVG(IF(e.event_type = \'click\', e.paid_amount, NULL)), 0) AS rpc');
        $this->column('IFNULL(AVG(IF(e.event_type = \'view\', e.paid_amount, NULL)), 0)*1000 AS rpm');
        $this->column('SUM(IF(e.event_type IN (\'click\', \'view\'), e.paid_amount, 0)) AS revenue');
    }

    public function setPublisherId(string $publisherId): self
    {
        $this->where(sprintf('e.publisher_id = 0x%s', $publisherId));

        return $this;
    }

    public function setDateRange(DateTime $dateStart, DateTime $dateEnd): self
    {
        $this->where(sprintf(
            'e.created_at BETWEEN \'%s\' AND \'%s\'',
            $this->convertDateTimeToMySqlDate($dateStart),
            $this->convertDateTimeToMySqlDate($dateEnd)
        ));

        return $this;
    }

    private function convertDateTimeToMySqlDate(DateTime $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s');
    }

    public function appendResolution(string $resolution): self
    {
        switch ($resolution) {
            case StatsRepository::HOUR_RESOLUTION:
                $this->column('YEAR(e.created_at) AS y');
                $this->column('MONTH(e.created_at) as m');
                $this->groupBy('YEAR(e.created_at)');
                $this->groupBy('MONTH(e.created_at)');
                $this->groupBy('DAY(e.created_at)');
                $this->groupBy('HOUR(e.created_at)');
                break;
            case StatsRepository::DAY_RESOLUTION:
                $this->column('YEAR(e.created_at) AS y');
                $this->column('MONTH(e.created_at) as m');
                $this->column('DAY(e.created_at) AS d');

                $this->groupBy('YEAR(e.created_at)');
                $this->groupBy('MONTH(e.created_at)');
                $this->groupBy('DAY(e.created_at)');
                break;
            case StatsRepository::WEEK_RESOLUTION:
                $this->column('YEARWEEK(e.created_at, 3) as yw');
                $this->groupBy('YEARWEEK(e.created_at, 3)');
                break;
            case StatsRepository::MONTH_RESOLUTION:
                $this->column('YEAR(e.created_at) AS y');
                $this->column('MONTH(e.created_at) as m');
                $this->groupBy('YEAR(e.created_at)');
                $this->groupBy('MONTH(e.created_at)');
                break;
            case StatsRepository::QUARTER_RESOLUTION:
                $this->column('YEAR(e.created_at) AS y');
                $this->column('QUARTER(e.created_at) as q');
                $this->groupBy('YEAR(e.created_at)');
                $this->groupBy('QUARTER(e.created_at)');
                break;
            case StatsRepository::YEAR_RESOLUTION:
            default:
                $this->column('YEAR(e.created_at) AS y');
                $this->groupBy('YEAR(e.created_at)');
                break;
        }

        return $this;
    }

    public function appendSiteIdWhereClause(string $siteId = null): self
    {
        $this->where(sprintf('e.site_id = 0x%s', $siteId));

        return $this;
    }

    public function appendZoneIdWhereClause(string $zoneId = null): self
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
        $this->having('ctr>0');
        $this->having('rpc>0');
        $this->having('rpm>0');
        $this->having('revenue>0');

        return $this;
    }

    public function appendDomainGroupBy(): self
    {
        $this->column('e.domain AS domain');
        $this->groupBy('e.domain');

        return $this;
    }
}
